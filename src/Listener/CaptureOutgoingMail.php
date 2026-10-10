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
     * Secondary capture point. On Flarum 2.x the transport wrapper
     * (AuditTransport) is the one that always sees a send, because core's
     * MutateEmail listener returns true and Laravel dispatches MessageSending
     * with until(), which stops the listener chain after core. This listener
     * is kept as a compatibility path for environments where the event does
     * reach it; capture() is idempotent per message object.
     *
     * NOTE: Laravel dispatches this event through the event dispatcher's
     * `until()` method, which cancels the email when a listener returns
     * `false`. This listener must always return null.
     */
    public function handle(MessageSending $event)
    {
        try {
            $message = $event->message;

            if ($message !== null) {
                $this->recorder->capture($message);
            }
        } catch (Throwable $e) {
            // Auditing must never break mail delivery.
        }

        return null;
    }
}
