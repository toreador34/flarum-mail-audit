<?php

/**
 * This file is part of the Toreador Mail Audit extension for Flarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace Toreador\MailAudit;

use Carbon\Carbon;
use Flarum\Settings\SettingsRepositoryInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use Toreador\MailAudit\Mail\SymfonyMessageExtractor;
use Toreador\MailAudit\Mail\TokenRedactor;

/**
 * Glue between the mail pipeline and the database.
 *
 * A message is captured by AuditTransport, the one place every send passes
 * through: in Flarum 2.x core's MutateEmail listener returns early from the
 * MessageSending event (which Laravel dispatches with until()), so extension
 * listeners registered after it are never called. The same transport then
 * finalises the row (authoritative, also sees failures); the MessageSent
 * listener is kept as a fallback.
 */
class MailAuditRecorder
{
    /**
     * @var MailAuditRepository
     */
    protected $repository;

    /**
     * @var SymfonyMessageExtractor
     */
    protected $extractor;

    /**
     * @var TokenRedactor
     */
    protected $redactor;

    /**
     * @var SettingsRepositoryInterface
     */
    protected $settings;

    /**
     * @var LoggerInterface|null
     */
    protected $logger;

    /**
     * Maps spl_object_id($message) => audit row id for the messages of the
     * current process that have been captured but not finalised yet.
     *
     * @var array<int, int>
     */
    protected $tracked = [];

    /**
     * @var int
     */
    protected $loggedErrors = 0;

    public function __construct(
        MailAuditRepository $repository,
        SymfonyMessageExtractor $extractor,
        TokenRedactor $redactor,
        SettingsRepositoryInterface $settings,
        ?LoggerInterface $logger = null
    ) {
        $this->repository = $repository;
        $this->extractor = $extractor;
        $this->redactor = $redactor;
        $this->settings = $settings;
        $this->logger = $logger;
    }

    public function isEnabled(): bool
    {
        return filter_var($this->settings->get('toreador-flarum-mail-audit.enabled', true), FILTER_VALIDATE_BOOLEAN);
    }

    public function capturesBody(): bool
    {
        return filter_var($this->settings->get('toreador-flarum-mail-audit.capture_body', true), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Which messages get their bodies stored when body capture is enabled:
     * `system` (default) only Flarum's own automatic mails, `all` every
     * outgoing message.
     */
    public function bodyScope(): string
    {
        $scope = $this->settings->get('toreador-flarum-mail-audit.body_scope', 'system');

        $scope = is_string($scope) ? strtolower(trim($scope)) : 'system';

        return in_array($scope, ['system', 'all'], true) ? $scope : 'system';
    }

    public function redactsTokens(): bool
    {
        return filter_var($this->settings->get('toreador-flarum-mail-audit.redact_tokens', true), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param object $message
     */
    protected function shouldStoreBodies($message): bool
    {
        if (! $this->capturesBody()) {
            return false;
        }

        if ($this->bodyScope() === 'all') {
            return true;
        }

        return $this->extractor->isSystemMail($message);
    }

    /**
     * Redact token-bearing URLs inside the JSON-encoded header list.
     *
     * @param mixed $json
     * @return mixed
     */
    protected function redactHeaders($json)
    {
        if (! is_string($json) || $json === '') {
            return $json;
        }

        try {
            $decoded = json_decode($json, true);

            if (! is_array($decoded)) {
                return $json;
            }

            foreach ($decoded as &$header) {
                if (is_array($header) && isset($header['value']) && is_string($header['value'])) {
                    $header['value'] = $this->redactor->redact($header['value']);
                }
            }

            unset($header);

            $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

            return $encoded === false ? $json : $encoded;
        } catch (Throwable $e) {
            return $json;
        }
    }

    /**
     * Persist the message that is about to be sent.
     *
     * @param  object $message
     * @return int|null The audit row id, or null when auditing is disabled or failed.
     */
    public function capture($message): ?int
    {
        if (! $this->isEnabled() || ! is_object($message)) {
            return null;
        }

        $objectId = spl_object_id($message);

        // The same message object may pass multiple capture points (the
        // MessageSending listener and the transport decorator); never insert
        // the same object twice.
        if (isset($this->tracked[$objectId])) {
            return $this->tracked[$objectId];
        }

        try {
            $this->extractor->ensureMessageId($message);

            $fields = $this->extractor->extract($message);

            if (! $this->shouldStoreBodies($message)) {
                $fields['body_html'] = null;
                $fields['body_text'] = null;
                $fields['has_html'] = false;
                $fields['has_text'] = false;
            } elseif ($this->redactsTokens()) {
                if ($fields['body_html'] !== null) {
                    $fields['body_html'] = $this->redactor->redact($fields['body_html']);
                }

                if ($fields['body_text'] !== null) {
                    $fields['body_text'] = $this->redactor->redact($fields['body_text']);
                }
            }

            if ($this->redactsTokens()) {
                $fields['headers'] = $this->redactHeaders($fields['headers'] ?? null);
            }

            $fields['status'] = 'pending';
            $fields['mailer'] = $this->stringOrNull($this->settings->get('mail_driver'), 50);
            $fields['created_at'] = Carbon::now()->toDateTimeString();

            $id = $this->repository->insert($fields);

            if ($id !== null) {
                $this->tracked[$objectId] = $id;
            }

            return $id;
        } catch (Throwable $e) {
            $this->logError('Could not capture outgoing mail', $e);

            return null;
        }
    }

    /**
     * Finalise the message as successfully handed to the transport.
     *
     * @param object $message
     */
    public function markSent($message): void
    {
        $this->finish($message, null);
    }

    /**
     * Finalise the message as failed.
     *
     * @param object    $message
     * @param Throwable $error
     */
    public function markFailed($message, Throwable $error): void
    {
        $this->finish($message, $error);
    }

    /**
     * @param object $message
     */
    protected function finish($message, ?Throwable $error): void
    {
        if (! is_object($message)) {
            return;
        }

        try {
            if (! $this->isEnabled()) {
                return;
            }

            $objectId = spl_object_id($message);
            $id = $this->tracked[$objectId] ?? null;

            if ($id !== null) {
                unset($this->tracked[$objectId]);
            }

            $messageId = $this->extractor->messageId($message);
            $errorText = $error !== null ? $this->formatError($error) : null;

            if ($id !== null) {
                if ($errorText !== null) {
                    $this->repository->markFailedById($id, $errorText);
                } else {
                    $this->repository->markSentById($id);
                }

                return;
            }

            if ($messageId === null || $messageId === '') {
                return;
            }

            if ($errorText !== null) {
                $this->repository->markFailedByMessageId($messageId, $errorText);
            } else {
                $this->repository->markSentByMessageId($messageId);
            }
        } catch (Throwable $e) {
            $this->logError('Could not finalise mail audit entry', $e);
        }
    }

    protected function formatError(Throwable $error): string
    {
        $message = get_class($error).': '.$error->getMessage();

        if (strlen($message) > 2000) {
            $message = substr($message, 0, 2000).'...';
        }

        return $message;
    }

    protected function stringOrNull($value, int $maxLength = 255): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = (string) $value;

        if ($value === '') {
            return null;
        }

        return strlen($value) > $maxLength ? substr($value, 0, $maxLength) : $value;
    }

    protected function logError(string $context, Throwable $error): void
    {
        // Log the first couple of failures loudly, then back off so a broken
        // installation cannot flood the log with one entry per email.
        if ($this->logger === null || $this->loggedErrors >= 3) {
            return;
        }

        $this->loggedErrors++;

        $this->logger->error("Toreador Mail Audit: $context: {$error->getMessage()}", [
            'exception' => $error,
        ]);
    }
}
