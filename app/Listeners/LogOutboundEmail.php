<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Mail\TrackedMail;
use App\Models\OutboundEmail;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Str;

/**
 * Record every tracked email once the mail server has accepted it.
 *
 * On MessageSent rather than at the point of sending, so a send that throws
 * leaves no row claiming an email went out. Untracked mail (anything not a
 * TrackedMail, such as the form notifications) is ignored.
 */
final class LogOutboundEmail
{
    public function handle(MessageSent $event): void
    {
        $headers = $event->message->getHeaders();
        $template = $headers->get(TrackedMail::TEMPLATE_HEADER)?->getBodyAsString();

        if ($template === null || $template === '') {
            return;
        }

        $submission = $headers->get(TrackedMail::SUBMISSION_HEADER)?->getBodyAsString();

        foreach ($event->message->getTo() as $address) {
            OutboundEmail::query()->create([
                'audit_submission_id' => Str::isUuid((string) $submission) ? $submission : null,
                'to_email' => mb_strtolower($address->getAddress()),
                'subject' => (string) $event->message->getSubject(),
                'template' => $template,
                'resend_message_id' => $event->sent->getMessageId(),
            ]);
        }
    }
}
