<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * A client opening a page of their preview: when, and which page. Nothing
 * about who.
 *
 * @property int $id
 * @property int $entry_id
 * @property string|null $page
 * @property CarbonImmutable $viewed_at
 */
final class PreviewView extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'viewed_at' => 'immutable_datetime',
        ];
    }
}
