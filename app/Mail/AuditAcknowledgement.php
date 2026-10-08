<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\AuditSubmission;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** To the prospect, straight after an /audit request. Live copy. */
final class AuditAcknowledgement extends TrackedMail
{
    public function __construct(public AuditSubmission $submission) {}

    protected function template(): string
    {
        return 'audit_acknowledgement';
    }

    protected function submissionId(): ?string
    {
        return $this->submission->id;
    }

    public function envelope(): Envelope
    {
        $replyTo = config('cg-cms.site.contact_email');

        return new Envelope(
            subject: "Your AI readiness audit is being prepared — {$this->submission->audit_ref}",
            replyTo: is_string($replyTo) && $replyTo !== '' ? [new Address($replyTo)] : [],
        );
    }

    public function content(): Content
    {
        $name = trim((string) ($this->submission->data['name'] ?? ''));

        return new Content(view: 'mail.audit-acknowledgement', with: [
            'firstName' => $name === '' ? 'there' : explode(' ', $name)[0],
            'auditRef' => $this->submission->audit_ref,
        ]);
    }
}
