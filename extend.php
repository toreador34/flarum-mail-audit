<?php

/**
 * This file is part of the Toreador Mail Audit extension for Flarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace Toreador\MailAudit;

use Flarum\Extend;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Toreador\MailAudit\Api\Controller\ClearMailsController;
use Toreador\MailAudit\Api\Controller\DeleteMailController;
use Toreador\MailAudit\Api\Controller\ListMailsController;
use Toreador\MailAudit\Api\Controller\ShowMailController;
use Toreador\MailAudit\Console\PruneMailAuditCommand;
use Toreador\MailAudit\Listener\CaptureOutgoingMail;
use Toreador\MailAudit\Listener\MarkMailAsSent;
use Toreador\MailAudit\Provider\MailAuditServiceProvider;

return [
    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/resources/less/admin.less'),

    new Extend\Locales(__DIR__.'/resources/locale'),

    (new Extend\ServiceProvider())
        ->register(MailAuditServiceProvider::class),

    (new Extend\Settings())
        ->default('toreador-flarum-mail-audit.enabled', true)
        ->default('toreador-flarum-mail-audit.capture_body', true)
        ->default('toreador-flarum-mail-audit.retention_days', 30),

    (new Extend\Event())
        ->listen(MessageSending::class, CaptureOutgoingMail::class)
        ->listen(MessageSent::class, MarkMailAsSent::class),

    (new Extend\Console())
        ->command(PruneMailAuditCommand::class)
        ->schedule(PruneMailAuditCommand::class, function (Event $event) {
            $event->daily();
        }),

    (new Extend\Routes('api'))
        ->get('/mail-audit/mails', 'toreador-flarum-mail-audit.mails', ListMailsController::class)
        ->post('/mail-audit/mails/clear', 'toreador-flarum-mail-audit.mails.clear', ClearMailsController::class)
        ->get('/mail-audit/mails/{id}', 'toreador-flarum-mail-audit.mail', ShowMailController::class)
        ->delete('/mail-audit/mails/{id}', 'toreador-flarum-mail-audit.mail.delete', DeleteMailController::class),
];
