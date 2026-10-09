<?php

/**
 * This file is part of the Toreador Mail Audit extension for Flarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace Toreador\MailAudit\Mail;

/**
 * Converts a Swift_Message (Swift Mailer 6, used by Flarum 1.x) into the
 * plain arrays that are stored in the mail_audit_logs table.
 *
 * Everything is defensive on purpose: auditing must never be the reason a
 * real email fails to go out, so every accessor is guarded.
 */
class SwiftMessageExtractor
{
    /**
     * Bodies larger than this are truncated before they are stored, so a
     * pathological message cannot blow up the database.
     */
    public const MAX_BODY_BYTES = 1048576;

    /**
     * @param  object $message A Swift_Mime_SimpleMessage instance.
     * @return array<string, mixed>
     */
    public function extract($message): array
    {
        $to = $this->addresses($message->getTo());
        $cc = $this->addresses($message->getCc());
        $bcc = $this->addresses($message->getBcc());

        [$html, $text] = $this->bodies($message);

        $attachments = $this->attachments($message);

        return [
            'message_id' => $this->stringOrNull($message->getId(), 190),
            'subject' => $this->stringOrNull($message->getSubject(), 2000),
            'from_json' => $this->encode($this->addresses($message->getFrom())),
            'to_json' => $this->encode($to),
            'cc_json' => $this->encode($cc),
            'bcc_json' => $this->encode($bcc),
            'reply_to_json' => $this->encode($this->addresses($message->getReplyTo())),
            'headers' => $this->encode($this->headers($message)),
            'body_html' => $html,
            'body_text' => $text,
            'attachments' => $this->encode($attachments),
            'recipient_count' => count($to) + count($cc) + count($bcc),
            'attachment_count' => count($attachments),
            'has_html' => $html !== null,
            'has_text' => $text !== null,
        ];
    }

    /**
     * @return array<int, array{address: string, name: string|null}>
     */
    protected function addresses($list): array
    {
        $addresses = [];

        foreach ((array) $list as $address => $name) {
            if (is_string($address) && $address !== '') {
                $addresses[] = [
                    'address' => $address,
                    'name' => $name !== null && $name !== '' ? (string) $name : null,
                ];
            } elseif (is_string($name) && $name !== '') {
                // Swift can return list-style arrays; be tolerant about it.
                $addresses[] = ['address' => $name, 'name' => null];
            }
        }

        return $addresses;
    }

    /**
     * @return array{0: string|null, 1: string|null} [html, text]
     */
    protected function bodies($message): array
    {
        $html = null;
        $text = null;

        $body = $this->content($message);
        $rootType = (string) $this->safeCall($message, 'getBodyContentType');

        foreach ($message->getChildren() as $child) {
            if ($child instanceof \Swift_Attachment) {
                continue;
            }

            $type = (string) $this->safeCall($child, 'getBodyContentType');
            $content = $this->content($child);

            if ($content === null || $content === '') {
                continue;
            }

            if ($html === null && stripos($type, 'text/html') === 0) {
                $html = $content;
            } elseif ($text === null && stripos($type, 'text/plain') === 0) {
                $text = $content;
            }
        }

        if ($body !== null && $body !== '') {
            if ($html === null && stripos($rootType, 'text/html') === 0) {
                $html = $body;
            } elseif ($text === null && stripos($rootType, 'text/plain') === 0) {
                $text = $body;
            }
        }

        return [
            $html !== null ? $this->truncate($html) : null,
            $text !== null ? $this->truncate($text) : null,
        ];
    }

    /**
     * @return array<int, array{name: string|null, content_type: string|null, size: int|null}>
     */
    protected function attachments($message): array
    {
        $attachments = [];

        foreach ($message->getChildren() as $child) {
            if (! $child instanceof \Swift_Attachment) {
                continue;
            }

            // getSize() only reads the optional Content-Disposition size
            // parameter, which is usually not set; fall back to the body.
            $size = (int) $this->safeCall($child, 'getSize');

            if ($size <= 0) {
                $content = $this->content($child);
                $size = $content !== null ? strlen($content) : 0;
            }

            $attachments[] = [
                'name' => $this->stringOrNull($this->safeCall($child, 'getFilename'), 255),
                'content_type' => $this->stringOrNull($this->safeCall($child, 'getContentType'), 255),
                'size' => $size > 0 ? $size : null,
            ];
        }

        return $attachments;
    }

    /**
     * @return array<int, array{name: string, value: string|null}>
     */
    protected function headers($message): array
    {
        $headers = [];

        try {
            foreach ($message->getHeaders()->getAll() as $header) {
                $headers[] = [
                    'name' => (string) $this->safeCall($header, 'getFieldName'),
                    'value' => $this->stringOrNull($this->safeCall($header, 'getFieldBody'), 4000),
                ];
            }
        } catch (\Throwable $e) {
            // ignore malformed headers
        }

        return $headers;
    }

    protected function content($entity): ?string
    {
        try {
            $body = $entity->getBody();
        } catch (\Throwable $e) {
            return null;
        }

        if (is_resource($body)) {
            $contents = stream_get_contents($body);
            $body = $contents === false ? null : $contents;
        }

        return is_string($body) ? $body : null;
    }

    protected function truncate(string $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        if (strlen($value) <= self::MAX_BODY_BYTES) {
            return $value;
        }

        return substr($value, 0, self::MAX_BODY_BYTES)."\n\n[... Mail Audit: body truncated at ".self::MAX_BODY_BYTES.' bytes ...]';
    }

    protected function safeCall($object, string $method)
    {
        if (! is_object($object) || ! method_exists($object, $method)) {
            return null;
        }

        try {
            return $object->{$method}();
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function stringOrNull($value, int $maxLength = 255): ?string
    {
        if ($value === null || ! is_scalar($value)) {
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
