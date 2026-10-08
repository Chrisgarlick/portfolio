<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A transactional email that was actually sent. Written by LogOutboundEmail
 * on MessageSent, never by the code that sends.
 *
 * @property string $to_email
 * @property string $subject
 * @property string $template
 */
final class OutboundEmail extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'outbound_email_log';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['sent_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<AuditSubmission, $this> */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(AuditSubmission::class, 'audit_submission_id');
    }
}
