<?php

/**
 * This file is part of the Toreador Mail Audit extension for Flarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace Toreador\MailAudit\Api\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Deletes every audit entry that matches an email address (sender, to, cc or
 * bcc). Supports erasure requests under GDPR Art. 17 / KVKK m. 7 without
 * clearing the entire audit trail.
 */
class PurgeMailsController extends AbstractMailAuditController
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->requireAdmin($request);

        $body = (array) $request->getParsedBody();

        $email = isset($body['email']) ? trim((string) $body['email']) : '';

        if ($email === '' || strlen($email) > 320 || ! str_contains($email, '@')) {
            return $this->json([
                'ok' => false,
                'error' => 'invalid_email',
            ], 422);
        }

        $deleted = $this->repository->purgeByEmail($email);

        return $this->json([
            'ok' => true,
            'email' => $email,
            'deleted' => $deleted,
        ]);
    }
}
