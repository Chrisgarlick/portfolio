<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditSubmission;
use App\Models\OutboundEmail;
use App\Models\ResourceLead;
use Cg\Cms\Models\FormSubmission;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Subject access request: everything held about one email, as JSON.
 *
 * The runbook's three SQL queries plus the resource leads, which it predates.
 * Attach the output to the reply.
 */
final class GdprExportCommand extends Command
{
    protected $signature = 'gdpr:export {email} {--output= : Write to this file instead of the console}';

    protected $description = 'Export everything held about an email address (subject access request)';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));

        $export = [
            'email' => $email,
            'exported_at' => now()->toIso8601String(),
            'audit_submissions' => AuditSubmission::query()
                ->whereRaw('lower(email) = ?', [$email])
                ->orderBy('submitted_at')
                ->get(['audit_ref', 'status', 'submitted_at', 'sent_at', 'deleted_at', 'deletion_reason', 'privacy_notice_version', 'data'])
                ->toArray(),
            'outbound_emails' => OutboundEmail::query()
                ->where('to_email', $email)
                ->orderByDesc('sent_at')
                ->get(['subject', 'template', 'sent_at', 'resend_message_id'])
                ->toArray(),
            'form_submissions' => self::formSubmissions($email)
                ->orderBy('created_at')
                ->get(['form', 'context', 'data', 'created_at', 'rejected_for'])
                ->toArray(),
            'resource_leads' => ResourceLead::query()
                ->whereRaw('lower(email) = ?', [$email])
                ->with('downloads:id,lead_id,resource_slug,format,created_at')
                ->get(['id', 'email', 'first_name', 'company', 'sector', 'source_slug', 'marketing_consent', 'created_at'])
                ->toArray(),
        ];

        $json = (string) json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $output = $this->option('output');

        if (is_string($output) && $output !== '') {
            file_put_contents($output, $json);
            $this->components->info("Written to {$output}.");
        } else {
            $this->line($json);
        }

        return self::SUCCESS;
    }

    /**
     * Form submissions carrying this email, under either key the forms use.
     *
     * @return Builder<FormSubmission>
     */
    public static function formSubmissions(string $email)
    {
        return FormSubmission::query()->where(fn ($q) => $q
            ->whereRaw("lower(data->>'email') = ?", [$email])
            ->orWhereRaw("lower(data->>'Email') = ?", [$email]));
    }
}
