<?php

declare(strict_types=1);

use App\Mail\TrackedMail;
use App\Models\AuditSubmission;
use App\Models\OutboundEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;

uses(RefreshDatabase::class);

/*
|------------------------------------------------------------------------------
| outbound_email_log
|------------------------------------------------------------------------------
|
| Written from MessageSent, so it records what reached the mail server rather
| than what the code meant to send. A subject access request reads it.
|
*/

function trackedMail(?string $submissionId = null): TrackedMail
{
    return new class($submissionId) extends TrackedMail
    {
        public function __construct(private readonly ?string $submission) {}

        public function envelope(): Envelope
        {
            return new Envelope(subject: 'Your audit is being prepared');
        }

        public function content(): Content
        {
            return new Content(htmlString: '<p>Hello</p>');
        }

        protected function template(): string
        {
            return 'audit_acknowledgement';
        }

        protected function submissionId(): ?string
        {
            return $this->submission;
        }
    };
}

it('logs a tracked email once it is sent, with its template and submission', function (): void {
    config(['mail.default' => 'array']);

    $submission = AuditSubmission::query()->create([
        'audit_ref' => 'CG-2026-001',
        'email' => 'prospect@example.com',
        'data' => [],
        'privacy_notice_version' => '1',
    ]);

    // sendNow, because TrackedMail is queued and the queue is sync anyway;
    // this keeps the test about the listener, not the queue.
    Mail::to('Prospect@Example.com')->sendNow(trackedMail($submission->id));

    $row = OutboundEmail::query()->sole();

    expect($row->to_email)->toBe('prospect@example.com')
        ->and($row->template)->toBe('audit_acknowledgement')
        ->and($row->subject)->toBe('Your audit is being prepared')
        ->and($row->audit_submission_id)->toBe($submission->id)
        ->and($row->resend_message_id)->not->toBeEmpty();
});

it('ignores mail that is not tracked', function (): void {
    config(['mail.default' => 'array']);

    Mail::to('someone@example.com')->sendNow(new class extends Mailable
    {
        public function content(): Content
        {
            return new Content(htmlString: '<p>Untracked</p>');
        }
    });

    expect(OutboundEmail::query()->count())->toBe(0);
});

it('records nothing when the send fails', function (): void {
    config(['mail.default' => 'failing', 'mail.mailers.failing' => ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'timeout' => 1]]);

    expect(fn () => Mail::to('someone@example.com')->sendNow(trackedMail()))->toThrow(TransportException::class);

    expect(OutboundEmail::query()->count())->toBe(0);
});

it('is queued, so a visitor never waits on SMTP', function (): void {
    expect(trackedMail())->toBeInstanceOf(ShouldQueue::class);
});
