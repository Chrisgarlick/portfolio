<?php

declare(strict_types=1);

namespace App\Mail;

use Cg\Cms\Models\FormSubmission;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The internal "new enquiry" note for a form submission.
 *
 * Queued like every TrackedMail, so the visitor's request returns as soon as
 * the submission is stored. Replying goes straight to the person who wrote,
 * when they gave a valid email address.
 */
final class FormSubmissionNotification extends TrackedMail
{
    /** @param  array<string, mixed>  $definition */
    public function __construct(
        public readonly FormSubmission $submission,
        public readonly array $definition,
    ) {}

    public function envelope(): Envelope
    {
        $email = $this->submission->data['email'] ?? null;

        return new Envelope(
            subject: 'New '.mb_strtolower((string) ($this->definition['name'] ?? 'form submission')),
            replyTo: is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? [new Address($email)] : [],
        );
    }

    public function content(): Content
    {
        $lines = ['Form: '.($this->definition['name'] ?? '')];

        if ($this->submission->context !== null) {
            $lines[] = "Context: {$this->submission->context}";
        }

        foreach ((array) $this->submission->data as $key => $value) {
            $lines[] = ucfirst(str_replace('_', ' ', (string) $key)).': '.(is_scalar($value) ? $value : json_encode($value));
        }

        return new Content(htmlString: nl2br(e(implode("\n", $lines))));
    }

    protected function template(): string
    {
        return 'form_submission_notify';
    }
}
