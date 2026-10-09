<?php

/**
 * This file is part of the Toreador Mail Audit extension for Flarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace Toreador\MailAudit\Listener;

use Illuminate\Mail\Events\MessageSent;
use Throwable;
use Toreador\MailAudit\MailAuditRecorder;

/**
 * Fallback finalisation. AuditTransport normally marks rows already (and it is
 * also the only place that sees failures), so this listener only matters when
 * a different transport wrapper intercepted the send.
 */
class MarkMailAsSent
{
    /**
     * @var MailAuditRecorder
     */
    protected $recorder;

    public function __construct(MailAuditRecorder $recorder)
    {
        $this->recorder = $recorder;
    }

    public function handle(MessageSent $event)
    {
        try {
            if (isset($event->message)) {
                $this->recorder->markSent($event->message);
            }
        } catch (Throwable $e) {
            // Auditing must never break mail delivery.
        }

        return null;
    }
}
