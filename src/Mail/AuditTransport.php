<?php

/**
 * This file is part of the Toreador Mail Audit extension for Flarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace Toreador\MailAudit\Mail;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;
use Throwable;
use Toreador\MailAudit\MailAuditRecorder;

/**
 * Wraps the real Symfony transport so a send can be recorded and finalised.
 *
 * Capturing happens here rather than in the MessageSending event because
 * Flarum 2.x core listens to that event too and returns early (MutateEmail
 * returns true, and Laravel dispatches MessageSending via until()), which
 * stops the listener chain before extension listeners run. The transport is
 * the one place every send is guaranteed to pass through, and it also sees
 * failures: a connection error (SMTP refused or unreachable, authentication
 * errors, ...) is thrown by the underlying transport and recorded before it
 * is re-thrown, while Laravel's MessageSent event is never dispatched for
 * failed sends.
 *
 * Note that Symfony's AbstractTransport clones the message internally before
 * handing it to the driver, so this decorator sees the original message
 * object (the same one a MessageSending listener would have seen).
 */
class AuditTransport implements TransportInterface
{
    /**
     * @var TransportInterface
     */
    protected $transport;

    /**
     * @var MailAuditRecorder
     */
    protected $recorder;

    public function __construct(TransportInterface $transport, MailAuditRecorder $recorder)
    {
        $this->transport = $transport;
        $this->recorder = $recorder;
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $this->recorder->capture($message);

        try {
            $sent = $this->transport->send($message, $envelope);
        } catch (Throwable $e) {
            $this->recorder->markFailed($message, $e);

            throw $e;
        }

        $this->recorder->markSent($message);

        return $sent;
    }

    public function __toString(): string
    {
        return (string) $this->transport;
    }
}
