<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\AuditSubmission;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** To the site owner, for every /audit request. Reply goes to the prospect. */
final class AuditRequestNotification extends TrackedMail
{
    /** Shown in their own rows; everything else is a sector-specific answer. */
    public const RESERVED = [
        'name', 'email', 'companyName', 'website', 'sector', 'teamSize',
        'biggestBottleneck', 'budgetRange', 'sixMonthWin', 'notes', 'referrer',
    ];

    public function __construct(public AuditSubmission $submission) {}

    protected function template(): string
    {
        return 'audit_internal_notify';
    }

    protected function submissionId(): ?string
    {
        return $this->submission->id;
    }

    public function envelope(): Envelope
    {
        $data = $this->submission->data;

        return new Envelope(
            subject: sprintf(
                'New audit request: %s (%s) — %s',
                $data['companyName'] ?? '',
                $data['sector'] ?? '',
                $this->submission->audit_ref,
            ),
            replyTo: [new Address($this->submission->email)],
        );
    }

    public function content(): Content
    {
        $data = $this->submission->data;

        return new Content(view: 'mail.audit-request-notification', with: [
            'submission' => $this->submission,
            'data' => $data,
            'sectorAnswers' => array_diff_key($data, array_flip(self::RESERVED)),
        ]);
    }
}
