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

class DeleteMailController extends AbstractMailAuditController
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->requireAdmin($request);

        $id = (int) $this->routeParam($request, 'id');
        $deleted = $this->repository->delete($id);

        if (! $deleted) {
            return $this->json(['error' => 'not_found'], 404);
        }

        return $this->json(['ok' => true, 'deleted' => true, 'id' => $id]);
    }
}
