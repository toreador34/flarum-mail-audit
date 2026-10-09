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
use Toreador\MailAudit\Mail\AuditSwiftMailer;
use Toreador\MailAudit\Mail\SwiftMessageExtractor;
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

        $this->container->singleton(SwiftMessageExtractor::class);

        $this->container->singleton(MailAuditRecorder::class, function (Container $container) {
            return new MailAuditRecorder(
                $container->make(MailAuditRepository::class),
                $container->make(SwiftMessageExtractor::class),
                $container->make(SettingsRepositoryInterface::class),
                $container->has('log') ? $container->make('log') : null
            );
        });

        $this->extendSwiftMailer();
    }

    /**
     * Wrap the Swift mailer so failed sends can be recorded as well.
     *
     * `swift.mailer` is resolved lazily by Flarum's mail service provider,
     * so decorating the binding here is enough for web requests as well as
     * for queue workers processing queued notification emails.
     */
    protected function extendSwiftMailer(): void
    {
        if (! class_exists('Swift_Mailer')) {
            // Flarum 2.x dropped Swift Mailer; the event listeners still work.
            return;
        }

        $this->container->extend('swift.mailer', function ($mailer, Container $container) {
            if (! $mailer instanceof \Swift_Mailer) {
                return $mailer;
            }

            return new AuditSwiftMailer(
                $mailer->getTransport(),
                $container->make(MailAuditRecorder::class)
            );
        });
    }
}
