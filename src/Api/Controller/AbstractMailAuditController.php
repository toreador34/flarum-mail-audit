<?php

/**
 * This file is part of the Toreador Mail Audit extension for Flarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace Toreador\MailAudit\Api\Controller;

use Flarum\Http\RequestUtil;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Toreador\MailAudit\MailAuditRepository;

abstract class AbstractMailAuditController implements RequestHandlerInterface
{
    /**
     * @var MailAuditRepository
     */
    protected $repository;

    public function __construct(MailAuditRepository $repository)
    {
        $this->repository = $repository;
    }

    protected function requireAdmin(ServerRequestInterface $request): void
    {
        if (! RequestUtil::getActor($request)->isAdmin()) {
            throw new ModelNotFoundException();
        }
    }

    protected function json(array $data, int $status = 200): JsonResponse
    {
        return new JsonResponse(
            $data,
            $status,
            [],
            JsonResponse::DEFAULT_JSON_FLAGS | JSON_INVALID_UTF8_SUBSTITUTE
        );
    }

    /**
     * Route parameters arrive as request attributes; query parameters are
     * accepted as a fallback.
     */
    protected function routeParam(ServerRequestInterface $request, string $key)
    {
        $value = $request->getAttribute($key);

        if ($value !== null) {
            return $value;
        }

        $query = $request->getQueryParams();

        return isset($query[$key]) ? $query[$key] : null;
    }
}
