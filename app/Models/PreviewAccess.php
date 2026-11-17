<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Cg\Cms\Models\Entry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * How a client gets into their preview: the token in their link, and a
 * password for when the link is lost. One row per `preview` entry, made when
 * the entry is first saved.
 *
 * Both are encrypted with the app key rather than hashed, so the studio can
 * show them again. Comparisons are constant-time.
 *
 * @property int $id
 * @property int $entry_id
 * @property string $token
 * @property string $password
 * @property CarbonImmutable|null $last_viewed_at
 * @property string|null $publish_status queued, publishing, published or failed
 * @property string|null $publish_error
 * @property string|null $published_version
 * @property CarbonImmutable|null $published_at
 */
final class PreviewAccess extends Model
{
    protected $table = 'preview_access';

    protected $guarded = [];

    protected $hidden = ['token', 'password'];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'password' => 'encrypted',
            'last_viewed_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Entry, $this> */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    /** The entry's access, made with a new token and password if it has none. */
    public static function for(Entry $entry): self
    {
        return self::query()->firstOrCreate(
            ['entry_id' => $entry->id],
            ['token' => self::newToken(), 'password' => self::newPassword()],
        );
    }

    /** 32 random bytes, URL-safe. */
    public static function newToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    /**
     * Easy to read out over the phone: three groups of four lower-case letters
     * and digits, with the look-alikes (0/o, 1/l/i) left out. About 62 bits.
     */
    public static function newPassword(): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';

        return collect(range(1, 3))
            ->map(fn (): string => collect(range(1, 4))->map(fn (): string => $alphabet[random_int(0, strlen($alphabet) - 1)])->implode(''))
            ->implode('-');
    }

    public function tokenMatches(string $token): bool
    {
        return $token !== '' && hash_equals($this->token, $token);
    }

    public function passwordMatches(string $password): bool
    {
        return $password !== '' && hash_equals($this->password, Str::lower(trim($password)));
    }

    /** The link to send: it signs the client in. */
    public function link(): string
    {
        return 'https://'.config('site.previews.host').'/'.$this->entry->slug.'/?k='.$this->token;
    }
}
