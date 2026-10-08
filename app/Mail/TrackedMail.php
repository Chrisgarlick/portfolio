<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * Every transactional email the site sends.
 *
 * Queued, always: a visitor should not wait on SMTP, and a mail outage should
 * not fail a request whose data is already safely stored. That was the live
 * site's rule too, enforced there by try/catch around every send.
 *
 * Each one names its template and, where it belongs to an audit request, the
 * submission. LogOutboundEmail reads both from the sent message and writes
 * outbound_email_log, so the log records what was actually delivered to the
 * mail server rather than what the code intended to send. That table is what
 * a subject access request draws on.
 */
abstract class TrackedMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public const TEMPLATE_HEADER = 'X-CG-Template';

    public const SUBMISSION_HEADER = 'X-CG-Submission';

    /** The template name recorded in outbound_email_log. */
    abstract protected function template(): string;

    /** The audit submission this email belongs to, if any. */
    protected function submissionId(): ?string
    {
        return null;
    }

    public function headers(): Headers
    {
        return new Headers(text: array_filter([
            self::TEMPLATE_HEADER => $this->template(),
            self::SUBMISSION_HEADER => $this->submissionId(),
        ]));
    }
}
