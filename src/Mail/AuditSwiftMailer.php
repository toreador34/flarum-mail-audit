<?php

/**
 * This file is part of the Toreador Mail Audit extension for Flarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace Toreador\MailAudit\Mail;

use RuntimeException;
use Swift_Mailer;
use Swift_Mime_SimpleMessage;
use Throwable;
use Toreador\MailAudit\MailAuditRecorder;

/**
 * Wraps the real Swift_Mailer so a send can be finalised as sent or failed.
 *
 * Extending Swift_Mailer (rather than wrapping the transport) is important:
 * Swift_Mailer::send() starts the transport *before* calling
 * Swift_Transport::send(), so connection errors (SMTP refused/unreachable)
 * are thrown by start() and never reach a transport decorator. It also
 * swallows Swift_RfcComplianceException internally and returns 0; we detect
 * that case via the "zero recipients accepted" check.
 */
class AuditSwiftMailer extends Swift_Mailer
{
    /**
     * @var MailAuditRecorder
     */
    protected $recorder;

    public function __construct(\Swift_Transport $transport, MailAuditRecorder $recorder)
    {
        parent::__construct($transport);

        $this->recorder = $recorder;
    }

    /**
     * {@inheritdoc}
     */
    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
    {
        try {
            $sent = parent::send($message, $failedRecipients);
        } catch (Throwable $e) {
            $this->recorder->markFailed($message, $e);

            throw $e;
        }

        // Swift_Mailer swallows RfcComplianceException and returns 0 instead.
        if ((int) $sent === 0 && $this->recipientCount($message) > 0) {
            $this->recorder->markFailed(
                $message,
                new RuntimeException('The mail transport accepted no recipients (Swift_Mailer returned 0, e.g. an invalid address).')
            );

            return $sent;
        }

        $this->recorder->markSent($message);

        return $sent;
    }

    protected function recipientCount(Swift_Mime_SimpleMessage $message): int
    {
        return count((array) $message->getTo()) + count((array) $message->getCc()) + count((array) $message->getBcc());
    }
}
