<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AuditSubmission;
use App\Models\GdprDeletion;
use Cg\Cms\Models\FormSubmission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Removing a prospect's audit data, in the two ways the runbook describes.
 *
 * Every erasure leaves a GdprDeletion row holding a hash of the email, never
 * the email, so there is a record that it happened without the record being
 * personal data itself.
 */
final class AuditErasure
{
    /**
     * The self-serve redaction behind /data/delete, as the live SQL did it.
     *
     * The row stays, stamped and emptied, so the studio still shows that a
     * request existed and was withdrawn. Goes further than the live endpoint
     * in two places, both because the page promises "all associated personal
     * data": the outbound email log entries for this submission, and the copy
     * mirrored into form_submissions, are deleted too. Both held the address.
     *
     * @return bool False when it had already been redacted.
     */
    public function redact(AuditSubmission $submission, string $reason): bool
    {
        if ($submission->isDeleted()) {
            return false;
        }

        $email = $submission->email;

        DB::transaction(function () use ($submission, $reason, $email): void {
            $counts = [
                'outbound_emails' => $submission->emails()->delete(),
                'form_submissions' => $this->mirrors($submission->audit_ref)->delete(),
                'pdf' => $this->deletePdf($submission->pdf_path) ? 1 : 0,
                'audit_submissions' => 1,
            ];

            $submission->forceFill([
                'deleted_at' => now(),
                'deletion_reason' => $reason,
                'email' => 'redacted@gdpr.local',
                'data' => [],
                'ip_address' => null,
                'user_agent' => null,
                'pdf_path' => null,
            ])->save();

            GdprDeletion::query()->create([
                'email_hash' => GdprDeletion::hash($email),
                'audit_ref' => $submission->audit_ref,
                'reason' => $reason,
                'counts' => $counts,
            ]);
        });

        return true;
    }

    /** Delete a stored PDF. True if a file was removed. */
    public function deletePdf(?string $path): bool
    {
        if ($path === null || $path === '') {
            return false;
        }

        $disk = Storage::disk('local');

        return $disk->exists($path) && $disk->delete($path);
    }

    /** @return Builder<FormSubmission> */
    public function mirrors(string $auditRef)
    {
        return FormSubmission::query()
            ->where('form', 'audit-intake')
            ->where(fn ($q) => $q->where('context', $auditRef)->orWhereRaw("data->>'auditRef' = ?", [$auditRef]));
    }
}
