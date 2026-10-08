<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An AI readiness audit request from /audit, and its review workflow.
 *
 * `deleted_at` is not Eloquent soft deletion: a self-serve erasure redacts the
 * row and stamps it, and the row must stay visible to the retention sweep and
 * the studio list as "deleted" rather than vanish from every query.
 *
 * @property string $id
 * @property string $audit_ref
 * @property string $email
 * @property array<string, mixed> $data
 * @property string $status submitted | reviewing | sent | ...
 * @property string|null $pdf_path
 * @property string|null $admin_notes
 * @property string|null $audit_markdown
 * @property CarbonImmutable|null $deleted_at
 */
final class AuditSubmission extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'submitted_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<OutboundEmail, $this> */
    public function emails(): HasMany
    {
        return $this->hasMany(OutboundEmail::class);
    }

    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }
}
