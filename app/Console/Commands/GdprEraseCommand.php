<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditSubmission;
use App\Models\GdprDeletion;
use App\Models\OutboundEmail;
use App\Models\ResourceLead;
use App\Support\AuditErasure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Right to erasure, for a request that came by email rather than the link.
 *
 * The runbook's manual SQL as one command: audit submissions (and their
 * PDFs), the outbound email log, form submissions and resource leads with
 * their downloads. --anonymise keeps the audit submissions as redacted rows
 * for reporting, as the runbook's alternative did. Either way a GdprDeletion
 * row records it, by hash, which replaces pasting into gdpr-deletions.log.
 */
final class GdprEraseCommand extends Command
{
    protected $signature = 'gdpr:erase {email}
        {--reason=subject requested erasure : Recorded in the deletion log}
        {--anonymise : Redact audit submissions instead of deleting them}
        {--force : Do not ask for confirmation}';

    protected $description = 'Erase everything held about an email address';

    public function handle(AuditErasure $erasure): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $reason = (string) $this->option('reason');

        $submissions = AuditSubmission::query()->whereRaw('lower(email) = ?', [$email])->get();
        $forms = GdprExportCommand::formSubmissions($email);
        $leads = ResourceLead::query()->whereRaw('lower(email) = ?', [$email]);
        $emails = OutboundEmail::query()->where('to_email', $email);

        $this->components->twoColumnDetail('Audit submissions', (string) $submissions->count());
        $this->components->twoColumnDetail('Outbound emails', (string) (clone $emails)->count());
        $this->components->twoColumnDetail('Form submissions', (string) (clone $forms)->count());
        $this->components->twoColumnDetail('Resource leads', (string) (clone $leads)->count());

        if (! $this->option('force') && ! $this->confirm("Erase all of this for {$email}?")) {
            $this->components->warn('Nothing changed.');

            return self::FAILURE;
        }

        $counts = DB::transaction(function () use ($submissions, $forms, $leads, $emails, $erasure, $reason): array {
            $pdfs = 0;

            foreach ($submissions as $submission) {
                $pdfs += $erasure->deletePdf($submission->pdf_path) ? 1 : 0;
                $erasure->mirrors($submission->audit_ref)->delete();
            }

            $counts = [
                'outbound_emails' => $emails->delete(),
                'form_submissions' => $forms->delete(),
                'resource_leads' => $leads->delete(),
                'pdf' => $pdfs,
            ];

            if ($this->option('anonymise')) {
                $counts['audit_submissions_redacted'] = AuditSubmission::query()
                    ->whereIn('id', $submissions->pluck('id'))
                    ->update([
                        'email' => 'redacted@gdpr.local',
                        'data' => '{}',
                        'ip_address' => null,
                        'user_agent' => null,
                        'pdf_path' => null,
                        'deleted_at' => now(),
                        'deletion_reason' => $reason.' '.now()->toDateString(),
                    ]);
            } else {
                $counts['audit_submissions'] = AuditSubmission::query()->whereIn('id', $submissions->pluck('id'))->delete();
            }

            return $counts;
        });

        GdprDeletion::query()->create([
            'email_hash' => GdprDeletion::hash($email),
            'audit_ref' => $submissions->pluck('audit_ref')->implode(', ') ?: null,
            'reason' => $reason,
            'counts' => $counts,
        ]);

        $this->components->info('Erased. Reply to confirm within 30 days of the request.');

        return self::SUCCESS;
    }
}
