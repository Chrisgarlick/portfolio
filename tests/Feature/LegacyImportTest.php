<?php

declare(strict_types=1);

use App\Models\User;
use Cg\Cms\Models\Entry;
use Cg\Cms\Models\FormSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
|------------------------------------------------------------------------------
| The import against the real thing
|------------------------------------------------------------------------------
|
| Runs only where a restore of the live Kritano database is reachable on the
| `legacy` connection (LEGACY_DB_* in .env; see plan section 21). Skipped
| everywhere else, including CI, rather than faked: an import tested against a
| hand-made fixture proves the fixture, and the point is the live data.
|
*/

beforeEach(function (): void {
    try {
        DB::connection('legacy')->table('articles')->limit(1)->count();
    } catch (Throwable) {
        $this->markTestSkipped('No restore of the live database on the legacy connection.');
    }
});

it('imports the live database and verifies it against the source', function (): void {
    // Verification includes every media file byte for byte, so it needs the
    // live media directory as well (LEGACY_MEDIA_DIR).
    $media = env('LEGACY_MEDIA_DIR');

    if (! is_string($media) || ! is_dir($media)) {
        $this->markTestSkipped('LEGACY_MEDIA_DIR is not set to a copy of the live media.');
    }

    Storage::fake('local');

    $this->artisan('site:import-legacy', ['--verify' => true, '--media' => $media])
        ->assertSuccessful();

    expect(Entry::query()->collection('article')->count())
        ->toBe(DB::connection('legacy')->table('articles')->count());
});

it('is idempotent: a second run changes no counts', function (): void {
    $sections = ['pages', 'articles', 'redirects', 'forms', 'leads', 'users'];

    $this->artisan('site:import-legacy', ['--only' => $sections])->assertSuccessful();
    $before = [Entry::query()->count(), FormSubmission::query()->count(), User::query()->count()];

    $this->artisan('site:import-legacy', ['--only' => $sections])->assertSuccessful();

    expect([Entry::query()->count(), FormSubmission::query()->count(), User::query()->count()])->toBe($before);
});

it('keeps the live publication dates rather than stamping today', function (): void {
    $this->artisan('site:import-legacy', ['--only' => ['articles']])->assertSuccessful();

    $live = DB::connection('legacy')->table('articles')->orderBy('slug')->first(['slug', 'updated_at']);
    $here = Entry::query()->collection('article')->where('slug', $live->slug)->first();

    expect($here->updated_at->toDateString())->toBe(substr((string) $live->updated_at, 0, 10));
});

it('carries the owner account over with a password Laravel can check, and not the bootstrap admin', function (): void {
    $this->artisan('site:import-legacy', ['--only' => ['users']])->assertSuccessful();

    $owner = User::query()->where('email', 'chris@chrisgarlick.com')->firstOrFail();

    $live = (string) DB::connection('legacy')->table('users')->where('email', 'chris@chrisgarlick.com')->value('password_hash');

    // Byte for byte the live hash after its four-character label, so the
    // live password still signs in.
    expect(str_starts_with($owner->password, '$2y$'))->toBeTrue()
        ->and(substr($owner->password, 4))->toBe(substr($live, 4))
        ->and(fn () => Hash::check('not-the-password', $owner->password))->not->toThrow(RuntimeException::class)
        ->and(User::query()->where('email', 'cms-admin@kritano.com')->exists())->toBeFalse();
});

it('passes the URL parity gate after an import', function (): void {
    $this->artisan('site:import-legacy', ['--only' => ['pages', 'articles', 'case_studies', 'resources', 'tools', 'proof_metrics', 'redirects']])
        ->assertSuccessful();

    $this->artisan('site:url-parity')->assertSuccessful();
});
