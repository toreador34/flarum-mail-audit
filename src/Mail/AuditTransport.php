<?php

/**
 * This file is part of the Toreador Mail Audit extension for Flarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace Toreador\MailAudit\Mail;

use Swift_Events_EventListener;
use Swift_Mime_SimpleMessage;
use Throwable;
use Toreador\MailAudit\MailAuditRecorder;

/**
 * Wraps the real Swift transport so we can tell a successful hand-off to the
 * mail server (SMTP/Mailgun/...) apart from a failed one.
 *
 * Laravel 8 (used by Flarum 1.x) only fires MessageSent when the transport
 * returned without throwing, but Swift_Mailer swallows RfcComplianceException
 * internally, so wrapping the transport is the only reliable place to observe
 * both outcomes. Failures are recorded and re-thrown untouched.
 */
class AuditTransport implements \Swift_Transport
{
    /**
     * @var \Swift_Transport
     */
    protected $transport;

    /**
     * @var MailAuditRecorder
     */
    protected $recorder;

    public function __construct(\Swift_Transport $transport, MailAuditRecorder $recorder)
    {
        $this->transport = $transport;
        $this->recorder = $recorder;
    }

    public function isStarted()
    {
        return $this->transport->isStarted();
    }

    public function start()
    {
        return $this->transport->start();
    }

    public function stop()
    {
        return $this->transport->stop();
    }

    public function ping()
    {
        return $this->transport->ping();
    }

    /**
     * {@inheritdoc}
     */
    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
    {
        try {
            $sent = $this->transport->send($message, $failedRecipients);
        } catch (Throwable $e) {
            $this->recorder->markFailed($message, $e);

            throw $e;
        }

        $this->recorder->markSent($message);

        return $sent;
    }

    public function registerPlugin(Swift_Events_EventListener $plugin)
    {
        $this->transport->registerPlugin($plugin);
    }
}
