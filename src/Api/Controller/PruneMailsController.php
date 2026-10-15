<?php

/**
 * This file is part of the Toreador Mail Audit extension for Flarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace Toreador\MailAudit\Api\Controller;

use Flarum\Settings\SettingsRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Toreador\MailAudit\MailAuditRepository;

/**
 * Applies the configured retention window immediately, so an admin does not
 * have to wait for the daily scheduled prune (or a cron entry).
 */
class PruneMailsController extends AbstractMailAuditController
{
    /**
     * @var SettingsRepositoryInterface
     */
    protected $settings;

    public function __construct(MailAuditRepository $repository, SettingsRepositoryInterface $settings)
    {
        parent::__construct($repository);

        $this->settings = $settings;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->requireAdmin($request);

        $body = (array) $request->getParsedBody();

        $days = isset($body['days']) ? (int) $body['days'] : (int) $this->settings->get('toreador-flarum-mail-audit.retention_days', 30);

        if ($days <= 0) {
            return $this->json([
                'ok' => false,
                'error' => 'no_retention_days',
            ], 422);
        }

        $deleted = $this->repository->prune($days);

        return $this->json([
            'ok' => true,
            'days' => $days,
            'deleted' => $deleted,
        ]);
    }
}
