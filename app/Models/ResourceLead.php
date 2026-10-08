<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Somebody who gave an email for a gated resource. One row per email.
 *
 * @property string $id
 * @property string $email
 * @property string|null $first_name
 * @property bool $marketing_consent
 */
final class ResourceLead extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'marketing_consent' => 'boolean',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<ResourceDownload, $this> */
    public function downloads(): HasMany
    {
        return $this->hasMany(ResourceDownload::class, 'lead_id');
    }
}
