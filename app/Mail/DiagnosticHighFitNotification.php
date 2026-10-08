<?php

declare(strict_types=1);

namespace App\Mail;

use App\Support\FitScore;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A high-fit diagnostic, to the inbox. Only high fits, as on the live site:
 * every submission would be noise.
 */
final class DiagnosticHighFitNotification extends TrackedMail
{
    /** @param array<string, mixed> $answers */
    public function __construct(
        public readonly array $answers,
        public readonly int $score,
        public readonly string $tier,
    ) {}

    public static function for(array $answers, FitScore $fit): self
    {
        return new self($answers, $fit->score, $fit->tier);
    }

    protected function template(): string
    {
        return 'diagnostic_high_fit';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf(
                'Diagnostic: high-fit lead (%s, %s)',
                (string) ($this->answers['businessType'] ?? ''),
                (string) ($this->answers['hours'] ?? ''),
            ),
            replyTo: filled($this->answers['email'] ?? null) ? [(string) $this->answers['email']] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.diagnostic-high-fit');
    }
}
