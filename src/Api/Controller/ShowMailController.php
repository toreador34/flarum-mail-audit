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

class ShowMailController extends AbstractMailAuditController
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->requireAdmin($request);

        $detail = $this->repository->detail((int) $this->routeParam($request, 'id'));

        if (! $detail) {
            return $this->json(['error' => 'not_found'], 404);
        }

        return $this->json(['data' => $detail]);
    }
}
