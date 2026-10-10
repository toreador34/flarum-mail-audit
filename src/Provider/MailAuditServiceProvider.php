<?php

/**
 * This file is part of the Toreador Mail Audit extension for Flarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace Toreador\MailAudit\Provider;

use Flarum\Foundation\AbstractServiceProvider;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Container\Container;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Toreador\MailAudit\Mail\AuditTransport;
use Toreador\MailAudit\Mail\SymfonyMessageExtractor;
use Toreador\MailAudit\MailAuditRecorder;
use Toreador\MailAudit\MailAuditRepository;

class MailAuditServiceProvider extends AbstractServiceProvider
{
    public function register()
    {
        $this->container->singleton(MailAuditRepository::class, function (Container $container) {
            return new MailAuditRepository(
                $container->make('flarum.db'),
                $container->has('log') ? $container->make('log') : null
            );
        });

        $this->container->singleton(SymfonyMessageExtractor::class);

        $this->container->singleton(MailAuditRecorder::class, function (Container $container) {
            return new MailAuditRecorder(
                $container->make(MailAuditRepository::class),
                $container->make(SymfonyMessageExtractor::class),
                $container->make(SettingsRepositoryInterface::class),
                $container->has('log') ? $container->make('log') : null
            );
        });

        $this->extendSymfonyTransport();
    }

    /**
     * Wrap the Symfony mail transport so failed sends can be recorded as well.
     *
     * Flarum 2.x binds `symfony.mailer.transport` as a singleton and resolves
     * it lazily when the mailer is built, so decorating the binding here is
     * enough for web requests as well as for queue workers processing queued
     * notification emails.
     */
    protected function extendSymfonyTransport(): void
    {
        $this->container->extend('symfony.mailer.transport', function ($transport, Container $container) {
            if (! $transport instanceof TransportInterface) {
                return $transport;
            }

            return new AuditTransport(
                $transport,
                $container->make(MailAuditRecorder::class)
            );
        });
    }
}
