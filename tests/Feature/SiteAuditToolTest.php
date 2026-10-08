<?php

declare(strict_types=1);

use App\Models\SiteAudit;
use Cg\Cms\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Sleep;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/*
|------------------------------------------------------------------------------
| The free site-audit tool
|------------------------------------------------------------------------------
|
| Queued and polled, rather than one request held open for a minute. The queue
| is sync in tests, so the POST returns after the job has run; the assertions
| are on what the job stored and what the poll endpoint reveals.
|
*/

beforeEach(function (): void {
    Sleep::fake();
    RateLimiter::clear('site-audit|127.0.0.1');
    config(['services.kritano.key' => 'test-key', 'services.kritano.url' => 'https://kritano.test/api/v1']);
});

function runAudit(array $body = ['url' => 'https://example.com']): TestResponse
{
    return test()->postJson('/api/tools/audit', $body);
}

it('queues a run, then stores the completed scores with the live overall', function (): void {
    Http::fake([
        'kritano.test/api/v1/audits' => Http::response(['id' => 'k-1']),
        'kritano.test/api/v1/audits/k-1' => Http::sequence()
            ->push(['status' => 'running'])
            ->push(['status' => 'completed', 'scores' => ['seo' => 90, 'accessibility' => 80, 'performance' => 70, 'security' => 60], 'issues' => [['rule' => 'title']]]),
    ]);

    $response = runAudit(['url' => 'https://example.com', 'task' => 'client intake'])->assertStatus(202);

    $audit = SiteAudit::query()->findOrFail($response->json('id'));

    expect($response->json('poll'))->toBe('/api/tools/audit/'.$audit->id)
        ->and($audit->status)->toBe('completed')
        ->and($audit->scores)->toEqual(['overall' => 75, 'seo' => 90, 'accessibility' => 80, 'performance' => 70])
        ->and($audit->task)->toBe('client intake')
        ->and($audit->kritano_audit_id)->toBe('k-1');

    Http::assertSent(fn ($request) => $request->url() === 'https://kritano.test/api/v1/audits'
        && $request['options'] === ['maxPages' => 1, 'maxDepth' => 1]
        && $request->hasHeader('Authorization', 'Bearer test-key'));
});

it('records a failed audit with the live message', function (): void {
    Http::fake([
        'kritano.test/api/v1/audits' => Http::response(['id' => 'k-2']),
        'kritano.test/api/v1/audits/k-2' => Http::response(['status' => 'failed']),
    ]);

    $id = runAudit()->json('id');

    $this->getJson("/api/tools/audit/{$id}")->assertOk()->assertJson([
        'status' => 'failed',
        'error' => 'Audit failed. The site may be unreachable.',
    ]);
});

it('gives up after thirty polls with the live timeout message', function (): void {
    Http::fake([
        'kritano.test/api/v1/audits' => Http::response(['id' => 'k-3']),
        'kritano.test/api/v1/audits/k-3' => Http::response(['status' => 'running']),
    ]);

    $id = runAudit()->json('id');

    expect(SiteAudit::query()->find($id)->error)->toBe('Audit is taking longer than expected. Please try again in a few minutes.');
    Sleep::assertSleptTimes(30);
});

it('reports an unavailable service when the create call fails', function (): void {
    Http::fake(['kritano.test/*' => Http::response([], 500)]);

    expect(SiteAudit::query()->find(runAudit()->json('id'))->error)
        ->toBe('Audit service temporarily unavailable. Please try again.');
});

it('stores clearly flagged mock scores when no API key is configured', function (): void {
    config(['services.kritano.key' => null]);
    Http::fake();

    $id = runAudit()->json('id');

    $this->getJson("/api/tools/audit/{$id}")
        ->assertOk()
        ->assertJson(['status' => 'completed', 'mock' => true])
        ->assertJsonMissingPath('scores.mock');

    Http::assertNothingSent();
});

it('refuses URLs the audit service could never reach', function (string $url): void {
    Http::fake();

    runAudit(['url' => $url])->assertStatus(400)->assertJson(['error' => 'Please provide a valid HTTP or HTTPS URL.']);

    expect(SiteAudit::query()->count())->toBe(0);
})->with([
    'ftp' => 'ftp://example.com',
    'localhost' => 'http://localhost:8000',
    'private ip' => 'http://192.168.1.10',
    'loopback' => 'http://127.0.0.1',
    'link-local' => 'http://169.254.169.254/latest/meta-data',
    'credentials' => 'https://user:pass@example.com',
    'no tld' => 'https://intranet',
]);

it('adds the scheme for someone who typed a bare domain', function (): void {
    config(['services.kritano.key' => null]);

    $id = runAudit(['url' => 'example.com'])->assertStatus(202)->json('id');

    expect(SiteAudit::query()->find($id)->url)->toBe('https://example.com');
});

it('never reveals the IP or the segmentation answer when polled', function (): void {
    config(['services.kritano.key' => null]);

    $id = runAudit(['url' => 'https://example.com', 'task' => 'private answer'])->json('id');

    $body = $this->getJson("/api/tools/audit/{$id}")->assertOk()->getContent();

    expect($body)->not->toContain('127.0.0.1')->not->toContain('private answer');
});

it('lists recent completed runs with domain and scores only, capped at fifty', function (): void {
    foreach (range(1, 55) as $n) {
        SiteAudit::query()->create(['url' => "https://site{$n}.com", 'domain' => "site{$n}.com", 'ip' => '10.0.0.1', 'status' => 'completed', 'scores' => ['overall' => 70, 'mock' => true]]);
    }
    SiteAudit::query()->create(['url' => 'https://pending.com', 'domain' => 'pending.com', 'status' => 'queued']);

    $data = $this->getJson('/api/tools/audit/recent?limit=500')->assertOk()->json('data');

    expect($data)->toHaveCount(50)
        ->and(array_keys($data[0]))->toBe(['domain', 'scores', 'created_at'])
        ->and($data[0]['scores'])->toBe(['overall' => 70])
        ->and(collect($data)->pluck('domain'))->not->toContain('pending.com');
});

it('limits each address to ten runs an hour, answering JSON', function (): void {
    config(['services.kritano.key' => null]);

    foreach (range(1, 10) as $n) {
        runAudit()->assertStatus(202);
    }

    runAudit()->assertStatus(429)->assertJson(['error' => 'Too many requests. Please try again later.']);
});

it('mounts the tool on the site-audit page and keeps the page cacheable', function (): void {
    Entry::query()->create([
        'collection' => 'tool',
        'slug' => 'site-audit',
        'title' => 'Site Audit',
        'status' => 'published',
        'data' => ['description' => 'Check your site.'],
    ]);

    $this->get('/tools/site-audit')
        ->assertOk()
        ->assertSee('data-site-audit', false)
        ->assertSee('/api/tools/audit', false)
        ->assertHeader('X-CG-Cache', 'MISS');
});
