<?php

/**
 * This file is part of the Toreador Mail Audit extension for Flarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace Toreador\MailAudit\Listener;

use Illuminate\Mail\Events\MessageSending;
use Throwable;
use Toreador\MailAudit\MailAuditRecorder;

class CaptureOutgoingMail
{
    /**
     * @var MailAuditRecorder
     */
    protected $recorder;

    public function __construct(MailAuditRecorder $recorder)
    {
        $this->recorder = $recorder;
    }

    /**
     * NOTE: Laravel 8 dispatches this event through the event dispatcher's
     * `until()` method, which cancels the email when a listener returns
     * `false`. This listener must always return null.
     */
    public function handle(MessageSending $event)
    {
        try {
            if (isset($event->message)) {
                $this->recorder->capture($event->message);
            }
        } catch (Throwable $e) {
            // Auditing must never break mail delivery.
        }

        return null;
    }
}
