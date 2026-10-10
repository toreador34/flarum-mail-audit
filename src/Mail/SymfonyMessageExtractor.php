<?php

/**
 * This file is part of the Toreador Mail Audit extension for Flarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace Toreador\MailAudit\Mail;

use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Header\HeaderInterface;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\Part\DataPart;
use Throwable;

/**
 * Converts a Symfony Mime message (used by Flarum 2.x, which replaced Swift
 * Mailer with Symfony Mailer) into the plain arrays that are stored in the
 * mail_audit_logs table.
 *
 * Everything is defensive on purpose: auditing must never be the reason a
 * real email fails to go out, so every accessor is guarded.
 */
class SymfonyMessageExtractor
{
    /**
     * Bodies larger than this are truncated before they are stored, so a
     * pathological message cannot blow up the database.
     */
    public const MAX_BODY_BYTES = 1048576;

    /**
     * Make sure the message carries a Message-ID before it is stored.
     *
     * Symfony only generates the Message-ID when the message is prepared for
     * sending (inside the transport), so at capture time the header is usually
     * missing. Generating it here means the audit row references the exact
     * Message-ID that ends up in the delivered email, which makes it possible
     * to match an audit entry against mail server logs.
     *
     * @param mixed $message A Symfony\Component\Mime\Email instance.
     */
    public function ensureMessageId($message): void
    {
        try {
            if (! $message instanceof Message) {
                return;
            }

            $headers = $message->getHeaders();

            if ($headers->has('Message-ID')) {
                return;
            }

            $headers->addIdHeader('Message-ID', $message->generateMessageId());
        } catch (Throwable $e) {
            // Without a From/Sender header Symfony cannot generate an ID.
        }
    }

    /**
     * @param mixed $message A Symfony\Component\Mime\Email instance.
     * @return string|null
     */
    public function messageId($message): ?string
    {
        try {
            if (! $message instanceof Message) {
                return null;
            }

            $value = $message->getHeaders()->getHeaderBody('Message-ID');

            // Symfony's IdHeader exposes its body as an array of IDs.
            if (is_array($value)) {
                $value = reset($value);
            }

            return $this->stringOrNull($value, 190);
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * @param mixed $message A Symfony\Component\Mime\Email instance.
     * @return array<string, mixed>
     */
    public function extract($message): array
    {
        $to = $this->addresses($this->call($message, 'getTo'));
        $cc = $this->addresses($this->call($message, 'getCc'));
        $bcc = $this->addresses($this->call($message, 'getBcc'));

        $html = $this->content($this->call($message, 'getHtmlBody'));
        $text = $this->content($this->call($message, 'getTextBody'));

        $attachments = $this->attachments($message);

        return [
            'message_id' => $this->messageId($message),
            'subject' => $this->stringOrNull($this->call($message, 'getSubject'), 2000),
            'from_json' => $this->encode($this->addresses($this->call($message, 'getFrom'))),
            'to_json' => $this->encode($to),
            'cc_json' => $this->encode($cc),
            'bcc_json' => $this->encode($bcc),
            'reply_to_json' => $this->encode($this->addresses($this->call($message, 'getReplyTo'))),
            'headers' => $this->encode($this->headers($message)),
            'body_html' => $html !== null ? $this->truncate($html) : null,
            'body_text' => $text !== null ? $this->truncate($text) : null,
            'attachments' => $this->encode($attachments),
            'recipient_count' => count($to) + count($cc) + count($bcc),
            'attachment_count' => count($attachments),
            'has_html' => $html !== null,
            'has_text' => $text !== null,
        ];
    }

    /**
     * @param  mixed $list A list of Symfony Address instances.
     * @return array<int, array{address: string, name: string|null}>
     */
    protected function addresses($list): array
    {
        $addresses = [];

        foreach (is_iterable($list) ? $list : [] as $address) {
            if ($address instanceof Address) {
                $name = $address->getName();

                $addresses[] = [
                    'address' => $address->getAddress(),
                    'name' => $name !== '' ? $name : null,
                ];
            } elseif ($address instanceof \Stringable) {
                $addresses[] = ['address' => (string) $address, 'name' => null];
            } elseif (is_string($address) && $address !== '') {
                $addresses[] = ['address' => $address, 'name' => null];
            }
        }

        return $addresses;
    }

    /**
     * @param  mixed $message A Symfony\Component\Mime\Email instance.
     * @return array<int, array{name: string|null, content_type: string|null, size: int|null}>
     */
    protected function attachments($message): array
    {
        $attachments = [];

        try {
            $parts = $message->getAttachments();
        } catch (Throwable $e) {
            return [];
        }

        foreach (is_iterable($parts) ? $parts : [] as $part) {
            if (! $part instanceof DataPart) {
                continue;
            }

            // Embedded (inline) images are not attachments; skip them like the
            // Swift Mailer era extractor did.
            try {
                if ($part->getDisposition() === 'inline') {
                    continue;
                }
            } catch (Throwable $e) {
                // carry on
            }

            $size = null;

            try {
                $size = strlen($part->getBody());
            } catch (Throwable $e) {
                $size = null;
            }

            $attachments[] = [
                'name' => $this->stringOrNull($this->call($part, 'getFilename'), 255),
                'content_type' => $this->stringOrNull($this->call($part, 'getContentType'), 255),
                'size' => $size !== null && $size > 0 ? $size : null,
            ];
        }

        return $attachments;
    }

    /**
     * @param  mixed $message A Symfony\Component\Mime\Email instance.
     * @return array<int, array{name: string, value: string|null}>
     */
    protected function headers($message): array
    {
        $headers = [];

        try {
            foreach ($message->getHeaders()->all() as $header) {
                if (! $header instanceof HeaderInterface) {
                    continue;
                }

                $headers[] = [
                    'name' => (string) $header->getName(),
                    'value' => $this->stringOrNull($header->getBodyAsString(), 4000),
                ];
            }
        } catch (Throwable $e) {
            // ignore malformed headers
        }

        return $headers;
    }

    /**
     * Read a body that Symfony stores as a string, resource or null.
     *
     * @param  mixed $body
     */
    protected function content($body): ?string
    {
        if (is_string($body)) {
            return $body;
        }

        if (! is_resource($body)) {
            return null;
        }

        try {
            $meta = stream_get_meta_data($body);

            if (empty($meta['seekable'])) {
                return null;
            }

            $contents = stream_get_contents($body, -1, 0);

            rewind($body);

            return $contents === false ? null : $contents;
        } catch (Throwable $e) {
            return null;
        }
    }

    protected function truncate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        if (strlen($value) <= self::MAX_BODY_BYTES) {
            return $value;
        }

        return substr($value, 0, self::MAX_BODY_BYTES)."\n\n[... Mail Audit: body truncated at ".self::MAX_BODY_BYTES.' bytes ...]';
    }

    /**
     * @param  mixed $object
     * @param  string $method
     * @return mixed
     */
    protected function call($object, string $method)
    {
        if (! is_object($object) || ! method_exists($object, $method)) {
            return null;
        }

        try {
            return $object->{$method}();
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * @param  mixed $value
     */
    protected function stringOrNull($value, int $maxLength = 255): ?string
    {
        if ($value === null || (! is_scalar($value) && ! $value instanceof \Stringable)) {
            return null;
        }

        $value = (string) $value;

        if ($value === '') {
            return null;
        }

        if (strlen($value) > $maxLength) {
            $value = substr($value, 0, $maxLength);
        }

        return $value;
    }

    /**
     * @param  mixed $value
     */
    protected function encode($value): ?string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

        return $json === false ? null : $json;
    }
}
