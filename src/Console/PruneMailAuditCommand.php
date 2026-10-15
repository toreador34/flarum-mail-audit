<?php

/**
 * This file is part of the Toreador Mail Audit extension for Flarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace Toreador\MailAudit\Console;

use Flarum\Console\AbstractCommand;
use Flarum\Settings\SettingsRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Toreador\MailAudit\MailAuditRepository;

class PruneMailAuditCommand extends AbstractCommand
{
    /**
     * @var MailAuditRepository
     */
    protected $repository;

    /**
     * @var SettingsRepositoryInterface
     */
    protected $settings;

    public function __construct(MailAuditRepository $repository, SettingsRepositoryInterface $settings)
    {
        $this->repository = $repository;
        $this->settings = $settings;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('mail-audit:prune')
            ->setDescription('Finalises stale mail audit entries and deletes entries older than the configured retention period.')
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'Override the retention in days and force a one-off prune even when automatic retention is disabled (0 keeps everything).')
            ->addOption('stale-minutes', null, InputOption::VALUE_REQUIRED, 'Mark pending entries older than this many minutes as failed.', '15');
    }

    protected function fire(): int
    {
        $staleMinutes = (int) $this->input->getOption('stale-minutes');

        if ($staleMinutes > 0) {
            $finalised = $this->repository->markStalePending($staleMinutes);

            if ($finalised > 0) {
                $this->info("Marked $finalised stale pending entr".($finalised === 1 ? 'y' : 'ies').' as failed.');
            }
        }

        $days = $this->input->getOption('days');

        if ($days === null) {
            $enabled = filter_var($this->settings->get('toreador-flarum-mail-audit.retention_enabled', true), FILTER_VALIDATE_BOOLEAN);

            if (! $enabled) {
                $this->info('Automatic retention deletion is disabled; nothing was deleted. Use --days=N to force a one-off prune.');

                return Command::SUCCESS;
            }

            $days = (int) $this->settings->get('toreador-flarum-mail-audit.retention_days', 30);
        }

        $days = (int) $days;

        if ($days > 0) {
            $deleted = $this->repository->prune($days);

            $this->info("Deleted $deleted mail audit entr".($deleted === 1 ? 'y' : 'ies')." older than $days day(s).");
        } else {
            $this->info('Retention is disabled; nothing was deleted.');
        }

        return Command::SUCCESS;
    }
}
