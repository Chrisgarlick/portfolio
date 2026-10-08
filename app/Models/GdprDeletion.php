<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The audit trail of an erasure: a hash of the email, never the email.
 *
 * @property string $email_hash
 * @property string|null $audit_ref
 * @property string $reason
 * @property array<string, int> $counts
 */
final class GdprDeletion extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'counts' => 'array',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    public static function hash(string $email): string
    {
        return hash('sha256', mb_strtolower(trim($email)));
    }
}
