<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\ResourceLead;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The download link, sent to the lead. Copy ported from server.ts. */
final class ResourceDelivery extends TrackedMail
{
    public function __construct(
        public readonly ResourceLead $lead,
        public readonly string $resourceTitle,
        public readonly string $url,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Your download: {$this->resourceTitle}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.resource-delivery');
    }

    protected function template(): string
    {
        return 'resource_delivery';
    }
}
