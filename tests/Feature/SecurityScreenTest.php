<?php

declare(strict_types=1);

use App\Models\User;
use Cg\Cms\Admin\AdminVite;
use Cg\Cms\Security\DependencyAudit;
use Cg\Cms\Security\VersionRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/*
|------------------------------------------------------------------------------
| The Security screen: advisories against the installed dependencies
|------------------------------------------------------------------------------
|
| Runs against the real composer.lock and package-lock.json, with the two
| advisory APIs faked.
*/

beforeEach(function (): void {
    Cache::forget(DependencyAudit::CACHE_KEY);
    $this->actingAs(User::factory()->create());
});

function installedComposerVersion(string $name): string
{
    $lock = json_decode((string) file_get_contents(base_path('composer.lock')), true);

    foreach ([...$lock['packages'], ...$lock['packages-dev']] as $package) {
        if ($package['name'] === $name) {
            return $package['version'];
        }
    }

    throw new RuntimeException("{$name} is not in composer.lock");
}

function fakeAdvisories(): void
{
    $laravel = installedComposerVersion('laravel/framework');

    Http::fake([
        'packagist.org/*' => Http::response(['advisories' => [
            'laravel/framework' => [
                ['title' => 'Affects what is installed', 'affectedVersions' => '>=1.0.0,<='.ltrim($laravel, 'v'), 'severity' => 'high', 'cve' => 'CVE-2026-0001', 'link' => 'https://example.com/a', 'reportedAt' => '2026-10-01 10:00:00'],
                ['title' => 'Already fixed in what is installed', 'affectedVersions' => '<1.0.0', 'severity' => 'critical'],
            ],
            'pestphp/pest' => [
                ['title' => 'A development tool', 'affectedVersions' => '>=0.1', 'severity' => 'medium'],
            ],
        ]]),
        'registry.npmjs.org/*' => Http::response([
            'vite' => [['title' => 'A build tool issue', 'severity' => 'moderate', 'vulnerable_versions' => '<99.0.0', 'url' => 'https://example.com/b']],
        ]),
    ]);
}

it('matches the version ranges advisories use', function (string $version, string $range, bool $expected): void {
    expect(VersionRange::matches($version, $range))->toBe($expected);
})->with([
    'inside a range' => ['2.10.1', '>=1.3.0,<=2.10.1', true],
    'just past it' => ['2.10.3', '>=1.3.0,<=2.10.1', false],
    'second group' => ['3.0.2', '>=1.0,<2.0|>=3.0.0,<3.0.4', true],
    'between groups' => ['2.5.0', '>=1.0,<2.0|>=3.0.0,<3.0.4', false],
    'v prefix' => ['v13.30.1', '<13.30.2', true],
    'space separated' => ['5.4.1', '>=5.0 <5.4.2', true],
    'wildcard' => ['2.1.7', '2.1.*', true],
    'empty range' => ['1.0.0', '', false],
]);

it('reports only the advisories that affect what is installed, production first', function (): void {
    fakeAdvisories();

    $report = app(DependencyAudit::class)->run();
    $titles = array_column($report['advisories'], 'title');

    expect($titles)->toContain('Affects what is installed', 'A development tool', 'A build tool issue')
        ->not->toContain('Already fixed in what is installed')
        ->and($report['advisories'][0]['title'])->toBe('Affects what is installed')
        ->and($report['advisories'][0]['dev'])->toBeFalse()
        ->and(collect($report['advisories'])->firstWhere('package', 'pestphp/pest')['dev'])->toBeTrue()
        ->and(collect($report['advisories'])->firstWhere('package', 'vite')['severity'])->toBe('medium')
        ->and($report['sources']['composer']['packages'])->toBeGreaterThan(50);

    // The sidebar badge counts production advisories only.
    expect(app(DependencyAudit::class)->productionCount())->toBe(1);
});

it('still reports one source when the other cannot be reached', function (): void {
    Http::fake([
        'packagist.org/*' => Http::response('Service unavailable', 503),
        'registry.npmjs.org/*' => Http::response([]),
    ]);

    $report = app(DependencyAudit::class)->run();

    expect($report['sources']['composer']['error'])->toContain('Packagist could not be reached')
        ->and($report['sources']['npm']['error'])->toBeNull()
        ->and($report['advisories'])->toBe([]);
});

it('shows the last report and checks again on request', function (): void {
    fakeAdvisories();
    $headers = ['X-Inertia' => 'true', 'X-Inertia-Version' => app(AdminVite::class)->version()];

    expect($this->get('/admin/security', $headers)->assertOk()->json('props.report'))->toBeNull();

    $this->post('/admin/security/check')->assertRedirect()->assertSessionHas('error');

    $props = $this->get('/admin/security', $headers)->assertOk()->json('props');

    expect($props['report']['advisories'])->toHaveCount(3)
        ->and($props['nav']['security'])->toBe(1);
});

it('runs from the console and fails while anything is affected', function (): void {
    fakeAdvisories();

    $this->artisan('cms:security-audit')
        ->expectsOutputToContain('laravel/framework')
        ->assertFailed();
});
