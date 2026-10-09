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

class ListMailsController extends AbstractMailAuditController
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->requireAdmin($request);

        $query = $request->getQueryParams();

        $limit = isset($query['limit']) ? (int) $query['limit'] : 20;
        if ($limit < 1 || $limit > 100) {
            $limit = 20;
        }

        $offset = isset($query['offset']) ? max(0, (int) $query['offset']) : 0;
        $status = isset($query['filter']['status']) ? (string) $query['filter']['status'] : null;
        $search = isset($query['filter']['qtext']) ? (string) $query['filter']['qtext'] : null;

        $list = $this->repository->list($limit, $offset, $status, $search);

        $data = [];
        foreach ($list['rows'] as $row) {
            $data[] = $this->repository->present($row);
        }

        return $this->json([
            'data' => $data,
            'total' => $list['total'],
            'limit' => $limit,
            'offset' => $offset,
            'hasMore' => $offset + count($data) < $list['total'],
            'stats' => $this->repository->stats(),
            'tableExists' => $this->repository->tableExists(),
        ]);
    }
}
