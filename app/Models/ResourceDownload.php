<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $lead_id
 * @property string $resource_slug
 * @property string $format
 */
final class ResourceDownload extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<ResourceLead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(ResourceLead::class, 'lead_id');
    }
}
