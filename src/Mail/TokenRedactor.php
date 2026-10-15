<?php

/**
 * This file is part of the Toreador Mail Audit extension for Flarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace Toreador\MailAudit\Mail;

/**
 * Redacts token-bearing URLs from stored bodies and headers.
 *
 * Flarum's password reset, email confirmation and notification unsubscribe
 * mails all embed one-time tokens in URLs. Keeping those tokens in the audit
 * trail would turn a database leak into account takeover material, so they
 * are replaced before anything is written to the database. The link is kept
 * up to the point where the secret begins, which preserves debugging value
 * ("which kind of link was sent") without storing the secret itself.
 *
 * Redaction is a best-effort heuristic and is intentionally conservative
 * about what it considers a token: known Flarum token routes and a list of
 * common token query parameters.
 */
class TokenRedactor
{
    public const REPLACEMENT = '[redacted]';

    /**
     * Path segments that are directly followed by a one-time token.
     */
    public const TOKEN_PATHS = 'reset|confirm|verify|activation|activate|unsubscribe|email-confirm|password-reset';

    /**
     * Query parameters that carry secrets.
     */
    public const TOKEN_QUERY_KEYS = 'token|code|secret|signature|hash|key|password|reset_token|password_token|access_token|auth_token';

    public function redact(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        // Path tokens, e.g. https://forum.test/reset/abc123 or
        // https://forum.test/notifications/unsubscribe/42/abc123
        $result = preg_replace(
            '#(https?://[^\s"\'<>]*?/(?:'.self::TOKEN_PATHS.')/)[^\s"\'<>]*#i',
            '$1'.self::REPLACEMENT,
            $value
        );

        if ($result !== null) {
            $value = $result;
        }

        // Query parameters, e.g. https://forum.test/page?token=abc123
        // `;` is included so HTML-escaped separators (&amp;token=...) match too.
        $result = preg_replace(
            '#([?&;](?:'.self::TOKEN_QUERY_KEYS.')=)[^&\s"\'<>]*#i',
            '$1'.self::REPLACEMENT,
            $value
        );

        if ($result !== null) {
            $value = $result;
        }

        return $value;
    }
}
