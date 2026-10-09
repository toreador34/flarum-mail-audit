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

class ClearMailsController extends AbstractMailAuditController
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->requireAdmin($request);

        $body = (array) $request->getParsedBody();

        $status = isset($body['status']) ? (string) $body['status'] : null;
        $olderThanDays = isset($body['older_than_days']) ? (int) $body['older_than_days'] : null;

        $deleted = $this->repository->clear($status ?: null, $olderThanDays);

        return $this->json([
            'ok' => true,
            'deleted' => $deleted,
        ]);
    }
}
