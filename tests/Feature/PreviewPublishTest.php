<?php

declare(strict_types=1);

use App\Jobs\PublishPreview;
use App\Models\PreviewAccess;
use App\Models\User;
use Cg\Cms\Lint\Linter;
use Cg\Cms\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|------------------------------------------------------------------------------
| Client previews: "Publish from Design" in the studio
|------------------------------------------------------------------------------
|
| The server fetches the preview bundle from Design with an export-only token,
| unpacks it as a new version, points `current` at it and keeps the last few.
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

it('publishes from Design: the frames in order, unpacked, then live', function (): void {
    Http::fake(['design.test/*' => fn () => Http::response(bundle(), 200, ['Content-Type' => 'application/zip'])]);
    $entry = publishable();
    $this->actingAs(User::factory()->create());

    $this->from('/studio/previews')->post("/studio/previews/{$entry->id}/publish")
        ->assertRedirect('/studio/previews')
        ->assertSessionHas('success');

    Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer dsn_export')
        && str_starts_with($r->url(), 'https://design.test/v1/files/abc123/export?')
        && $r['format'] === 'preview'
        && $r['frames'] === 'gj_home,gj_home_mobile,pre_product');

    $access = PreviewAccess::for($entry);
    expect($access->publish_status)->toBe('published')
        ->and($access->published_at)->not->toBeNull()
        ->and(readlink($this->previews.'/flexi/current'))->toBe($access->published_version)
        ->and(file_get_contents($this->previews.'/flexi/current/index.html'))->toContain('viewer')
        ->and(is_file($this->previews.'/flexi/current/pages/home-1440-1.webp'))->toBeTrue();
});

it('keeps the last three versions', function (): void {
    Http::fake(['design.test/*' => fn () => Http::response(bundle())]);
    $entry = publishable();
    foreach (range(1, 4) as $i) {
        $this->travel(1)->minutes();
        (new PublishPreview($entry->id))->handle();
    }

    $versions = glob($this->previews.'/flexi/[0-9]*-[0-9]*', GLOB_ONLYDIR);
    expect($versions)->toHaveCount(3)
        ->and(basename((string) readlink($this->previews.'/flexi/current')))->toBe(basename(max($versions)));
});

it('says why a publish failed, and leaves the live version alone', function (): void {
    $entry = publishable();
    $reply = fn () => Http::response(bundle());
    Http::fake(['design.test/*' => function () use (&$reply) {
        return $reply();
    }]);
    (new PublishPreview($entry->id))->handle();
    $live = readlink($this->previews.'/flexi/current');

    foreach ([
        [fn () => Http::response(['detail' => 'this token can only download exports'], 403), 'Design answered 403'],
        [fn () => Http::response('not a zip'), "isn't a zip"],
        [fn () => Http::response(bundle(without: ['manifest.json'])), 'no manifest.json'],
        [fn () => Http::response(bundle(['../escape.txt' => 'x'])), 'unsafe path'],
    ] as [$next, $why]) {
        $reply = $next;
        $this->travel(1)->minutes();
        (new PublishPreview($entry->id))->handle();
        $access = PreviewAccess::for($entry);
        expect($access->publish_status)->toBe('failed')
            ->and($access->publish_error)->toContain($why)
            ->and(readlink($this->previews.'/flexi/current'))->toBe($live);
    }
    expect(file_exists($this->previews.'/escape.txt'))->toBeFalse();
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
    Http::fake(['design.test/*' => fn () => Http::response(bundle())]);
    $entry = publishable();
    $this->actingAs(User::factory()->create());

    $this->get('/studio/previews')->assertOk()->assertSee('Not published from here yet')->assertSee('Publish from Design')->assertSee('https://design.test/files/abc123', false);

    (new PublishPreview($entry->id))->handle();
    $this->get('/studio/previews')->assertOk()->assertSee('Published ');
});

it('reads frames separated by commas or new lines', function (): void {
    $entry = publishable(['frames' => " a,b\n\nc ,  d "]);
    expect(PublishPreview::frames($entry))->toBe(['a', 'b', 'c', 'd']);
    $entry->data = ['frames' => ''];
    expect(PublishPreview::frames($entry))->toBe([]);
});
