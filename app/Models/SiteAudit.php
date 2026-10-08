<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One run of the free site-audit tool. Table name kept from the live site.
 *
 * @property string $id
 * @property string $url
 * @property string|null $domain
 * @property array<string, mixed>|null $scores
 * @property array<int|string, mixed>|null $issues
 * @property string $status queued | running | completed | failed
 * @property string|null $error
 */
final class SiteAudit extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'audit_logs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'scores' => 'array',
            'issues' => 'array',
            'completed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'failed'], true);
    }
}
