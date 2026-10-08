<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditSubmission;
use App\Models\OutboundEmail;
use App\Support\AuditErasure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * The retention sweep, from the runbook and the privacy notice.
 *
 *   unsent submissions       deleted 90 days after submission
 *   sent submissions         deleted 24 months after sending
 *   orphaned email log rows  deleted after 24 months
 *   orphaned audit PDFs      deleted
 *
 * Scheduled quarterly in routes/console.php, where the live site relied on
 * somebody remembering. A client worth keeping for seven years should be
 * archived elsewhere before their 24 months are up, as the runbook says.
 */
final class GdprSweepCommand extends Command
{
    protected $signature = 'gdpr:sweep {--dry-run : Report what would be deleted without deleting it}';

    protected $description = 'Delete audit data past its retention period';

    public function handle(AuditErasure $erasure): int
    {
        $dry = (bool) $this->option('dry-run');

        $unsent = AuditSubmission::query()->where('status', '!=', 'sent')->where('submitted_at', '<', now()->subDays(90));
        $sent = AuditSubmission::query()->where('status', 'sent')->where('sent_at', '<', now()->subMonths(24));
        $orphanEmails = OutboundEmail::query()->whereNull('audit_submission_id')->where('sent_at', '<', now()->subMonths(24));

        // Counted before anything is deleted, so the report is the same
        // whether or not this is a dry run.
        $counts = [
            'unsent submissions over 90 days' => (clone $unsent)->count(),
            'sent submissions over 24 months' => (clone $sent)->count(),
            'orphaned email log rows' => (clone $orphanEmails)->count(),
        ];

        $expired = (clone $unsent)->get()->merge((clone $sent)->get());

        if (! $dry) {
            foreach ($expired as $submission) {
                $erasure->deletePdf($submission->pdf_path);
                $erasure->mirrors($submission->audit_ref)->delete();
                $submission->delete();
            }

            $orphanEmails->delete();
        }

        $orphanPdfs = $this->orphanedPdfs();
        $counts['orphaned PDFs'] = count($orphanPdfs);

        if (! $dry) {
            Storage::disk('local')->delete($orphanPdfs);
        }

        $this->components->info($dry ? 'Dry run. Nothing was deleted.' : 'Retention sweep complete.');

        foreach ($counts as $label => $count) {
            $this->components->twoColumnDetail(($dry ? 'Would delete ' : 'Deleted ').$label, (string) $count);
        }

        return self::SUCCESS;
    }

    /**
     * PDFs on disk that no submission points at any more.
     *
     * @return array<int, string>
     */
    private function orphanedPdfs(): array
    {
        $kept = AuditSubmission::query()->whereNotNull('pdf_path')->pluck('pdf_path')->all();

        return array_values(array_diff(Storage::disk('local')->files('audits'), $kept));
    }
}
