<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\AuditSubmission;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The link the privacy page's "delete your data" form sends: a signed link to
 * /data/delete for one submission, good for a day. Logged against that
 * submission, so the erasure it leads to removes this email's log entry too.
 */
final class DataDeletionLink extends TrackedMail
{
    public function __construct(
        public readonly AuditSubmission $submission,
        public readonly string $url,
        public readonly int $hours,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Delete your data from chrisgarlick.com');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.data-deletion-link');
    }

    protected function template(): string
    {
        return 'data_deletion_link';
    }

    protected function submissionId(): ?string
    {
        return $this->submission->id;
    }
}
