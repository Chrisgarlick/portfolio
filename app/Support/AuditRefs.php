<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AuditSubmission;

/**
 * Audit references: CG-YYYY-NNN, numbered from 001 each year.
 *
 * Read the highest number used this year and add one. Two requests can read
 * the same number at the same moment; the unique index on audit_ref makes the
 * second insert fail, and the controller asks for the next number and tries
 * once more, as the live server did. Not final, so a test can stand in for the
 * losing side of that race.
 */
class AuditRefs
{
    public function next(): string
    {
        $year = now()->year;

        $max = (int) AuditSubmission::query()
            ->where('audit_ref', 'like', "CG-{$year}-%")
            ->selectRaw("coalesce(max(cast(split_part(audit_ref, '-', 3) as integer)), 0) as max_n")
            ->value('max_n');

        return sprintf('CG-%d-%03d', $year, $max + 1);
    }
}
