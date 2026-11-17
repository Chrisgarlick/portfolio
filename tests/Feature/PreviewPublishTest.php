<?php

declare(strict_types=1);

use App\Jobs\CheckPreviewExport;
use App\Jobs\PublishPreview;
use App\Models\PreviewAccess;
use App\Models\User;
use App\Services\DesignExports;
use App\Support\PreviewInstaller;
use Cg\Cms\Lint\Linter;
use Cg\Cms\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|------------------------------------------------------------------------------
| Client previews: "Publish from Design" in the studio
|------------------------------------------------------------------------------
|
| Publishing starts a background export in Design with an export-only token,
| checks on it until it's done (or failed, or stopped in Design's Exports
| panel), then unpacks it as a new version and points `current` at it.
|
*/

beforeEach(function (): void {
    $this->previews = storage_path('framework/testing/previews-'.Str::random(8));
    config([
        'site.previews.path' => $this->previews,
        'site.previews.design_url' => 'https://design.test',
        'site.previews.design_token' => 'dsn_export',
        'site.previews.keep' => 3,
    ]);
});

afterEach(function (): void {
    exec('rm -rf '.escapeshellarg($this->previews));
});

/** A preview bundle as Design makes one, optionally missing a file or carrying a bad path. */
function bundle(array $extra = [], array $without = []): string
{
    $path = tempnam(sys_get_temp_dir(), 'bundle-');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    foreach (['index.html' => '<!doctype html><title>viewer</title>', 'viewer.js' => '//', 'viewer.css' => '/**/', 'manifest.json' => '{"version":1}', 'pages/home-1440-1.webp' => 'RIFF'] + $extra as $name => $body) {
        if (! in_array($name, $without, true)) {
            $zip->addFromString($name, $body);
        }
    }
    $zip->close();
    $bytes = (string) file_get_contents($path);
    unlink($path);

    return $bytes;
}

function publishable(array $data = []): Entry
{
    return Linter::without(fn () => Entry::query()->create([
        'collection' => 'preview',
        'slug' => 'flexi',
        'title' => 'FLEXi',
        'status' => 'published',
        'data' => ['design_file' => 'abc123', 'frames' => "gj_home, gj_home_mobile\npre_product", ...$data],
    ]));
}

/**
 * A fake Design: an export that reports each of $states in turn when asked,
 * then serves $zip (a closure, so each download gets a fresh body).
 */
function fakeDesign(array $states, ?Closure $zip = null): void
{
    $zip ??= fn () => Http::response(bundle());
    // A fresh fake each time: Http::fake() adds to the stubs it already has,
    // and the first match wins.
    Http::swap(new Factory(app('events')));
    Http::fake([
        'design.test/v1/files/*/exports' => Http::response(['id' => 'ex1', 'status' => 'queued'], 202),
        'design.test/v1/exports/ex1/download' => $zip,
        'design.test/v1/exports/ex1' => function () use (&$states) {
            $state = count($states) > 1 ? array_shift($states) : $states[0];

            return Http::response(is_array($state) ? $state : ['id' => 'ex1', 'status' => $state]);
        },
    ]);
}

it('publishes from Design: starts an export with the frames, waits for it, then puts it live', function (): void {
    fakeDesign([['id' => 'ex1', 'status' => 'running', 'progress_done' => 3, 'progress_total' => 16], 'done']);
    $entry = publishable();
    $this->actingAs(User::factory()->create());

    $this->from('/studio/previews')->post("/studio/previews/{$entry->id}/publish")
        ->assertRedirect('/studio/previews')
        ->assertSessionHas('success');

    Http::assertSent(fn (Request $r) => $r->method() === 'POST'
        && $r->url() === 'https://design.test/v1/files/abc123/exports'
        && $r->hasHeader('Authorization', 'Bearer dsn_export')
        && $r['format'] === 'preview'
        && $r['frames'] === ['gj_home', 'gj_home_mobile', 'pre_product']);
    Http::assertSent(fn (Request $r) => $r->url() === 'https://design.test/v1/exports/ex1/download');

    $access = PreviewAccess::for($entry);
    expect($access->publish_status)->toBe('published')
        ->and($access->published_at)->not->toBeNull()
        ->and($access->publish_progress)->toBeNull()
        ->and(readlink($this->previews.'/flexi/current'))->toBe($access->published_version)
        ->and(file_get_contents($this->previews.'/flexi/current/index.html'))->toContain('viewer');
});

it('notes progress while Design works, and checks again later', function (): void {
    Queue::fake();
    fakeDesign([['id' => 'ex1', 'status' => 'running', 'progress_done' => 7, 'progress_total' => 16]]);
    $entry = publishable();
    PreviewAccess::for($entry)->update(['export_id' => 'ex1', 'publish_status' => 'publishing', 'publish_started_at' => now()]);

    (new CheckPreviewExport($entry->id))->handle(app(DesignExports::class), app(PreviewInstaller::class));

    expect(PreviewAccess::for($entry)->publish_progress)->toBe('7 / 16');
    Queue::assertPushed(CheckPreviewExport::class, fn ($job) => $job->entryId === $entry->id && $job->delay !== null);
    $this->actingAs(User::factory()->create());
    $this->get('/studio/previews')->assertSee('Publishing… 7 / 16');
});

it('gives up after an hour, and says it was stopped when it was', function (): void {
    Queue::fake();
    $entry = publishable();
    $check = fn () => (new CheckPreviewExport($entry->id))->handle(app(DesignExports::class), app(PreviewInstaller::class));

    fakeDesign(['running']);
    PreviewAccess::for($entry)->update(['export_id' => 'ex1', 'publish_status' => 'publishing', 'publish_started_at' => now()->subMinutes(61)]);
    $check();
    expect(PreviewAccess::for($entry)->publish_status)->toBe('failed')
        ->and(PreviewAccess::for($entry)->publish_error)->toContain('after an hour');

    fakeDesign(['cancelled']);
    PreviewAccess::for($entry)->update(['publish_status' => 'publishing', 'publish_started_at' => now()]);
    $check();
    expect(PreviewAccess::for($entry)->publish_error)->toBe('Stopped in Design.');

    fakeDesign([['id' => 'ex1', 'status' => 'failed', 'error' => 'interrupted by a restart']]);
    PreviewAccess::for($entry)->update(['publish_status' => 'publishing']);
    $check();
    expect(PreviewAccess::for($entry)->publish_error)->toContain('interrupted by a restart');
});

it('keeps the last three versions', function (): void {
    fakeDesign(['done']);
    $entry = publishable();
    foreach (range(1, 4) as $i) {
        $this->travel(1)->minutes();
        (new PublishPreview($entry->id))->handle(app(DesignExports::class));
    }

    $versions = glob($this->previews.'/flexi/[0-9]*-[0-9]*', GLOB_ONLYDIR);
    expect($versions)->toHaveCount(3)
        ->and(basename((string) readlink($this->previews.'/flexi/current')))->toBe(basename(max($versions)));
});

it('says why a publish failed, and leaves the live version alone', function (): void {
    $entry = publishable();
    fakeDesign(['done']);
    (new PublishPreview($entry->id))->handle(app(DesignExports::class));
    $live = readlink($this->previews.'/flexi/current');

    foreach ([
        [fn () => Http::response(['detail' => 'not finished'], 409), 'Design answered 409'],
        [fn () => Http::response('not a zip'), "isn't a zip"],
        [fn () => Http::response(bundle(without: ['manifest.json'])), 'no manifest.json'],
        [fn () => Http::response(bundle(['../escape.txt' => 'x'])), 'unsafe path'],
    ] as [$zip, $why]) {
        fakeDesign(['done'], $zip);
        $this->travel(1)->minutes();
        (new PublishPreview($entry->id))->handle(app(DesignExports::class));
        $access = PreviewAccess::for($entry);
        expect($access->publish_status)->toBe('failed')
            ->and($access->publish_error)->toContain($why)
            ->and(readlink($this->previews.'/flexi/current'))->toBe($live);
    }
    expect(file_exists($this->previews.'/escape.txt'))->toBeFalse();

    Http::swap(new Factory(app('events')));
    Http::fake(['design.test/*' => Http::response(['detail' => 'this token can only download exports'], 403)]);
    (new PublishPreview($entry->id))->handle(app(DesignExports::class));
    expect(PreviewAccess::for($entry)->publish_error)->toContain('Design answered 403');
});

it('refuses without a Design file or a token, and only for signed-in admins', function (): void {
    Http::fake();
    $entry = publishable(['design_file' => '']);

    $this->post("/studio/previews/{$entry->id}/publish")->assertRedirect('/admin/signin');

    $this->actingAs(User::factory()->create());
    $this->post("/studio/previews/{$entry->id}/publish")->assertSessionHas('error');

    $entry->update(['data' => ['design_file' => 'abc123']]);
    config(['site.previews.design_token' => null]);
    $this->post("/studio/previews/{$entry->id}/publish")->assertSessionHas('error', fn ($m) => str_contains($m, 'DESIGN_PREVIEW_TOKEN'));

    Http::assertNothingSent();
    expect(PreviewAccess::for($entry)->publish_status)->toBeNull();
});

it('shows the publish state and the button in the studio', function (): void {
    fakeDesign(['done']);
    $entry = publishable();
    $this->actingAs(User::factory()->create());

    $this->get('/studio/previews')->assertOk()->assertSee('Not published from here yet')->assertSee('Publish from Design')->assertSee('https://design.test/files/abc123', false);

    (new PublishPreview($entry->id))->handle(app(DesignExports::class));
    $this->get('/studio/previews')->assertOk()->assertSee('Published ');
});

it('reads frames separated by commas or new lines', function (): void {
    $entry = publishable(['frames' => " a,b\n\nc ,  d "]);
    expect(PublishPreview::frames($entry))->toBe(['a', 'b', 'c', 'd']);
    $entry->data = ['frames' => ''];
    expect(PublishPreview::frames($entry))->toBe([]);
});
