<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\ResourceLead;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The internal "new resource lead" note. Copy ported from server.ts. */
final class ResourceLeadNotification extends TrackedMail
{
    public function __construct(
        public readonly ResourceLead $lead,
        public readonly string $resourceTitle,
        public readonly string $slug,
        public readonly string $ip,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Resource lead: {$this->lead->email} → {$this->resourceTitle}",
            replyTo: [new Address($this->lead->email)],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.resource-lead-notification');
    }

    protected function template(): string
    {
        return 'resource_lead_notify';
    }
}
