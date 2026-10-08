<?php

declare(strict_types=1);

use Cg\Cms\Cdn\CdnPurger;
use Cg\Cms\Cdn\CloudflarePurger;
use Cg\Cms\Cdn\NullPurger;
use Cg\Cms\Jobs\PurgeCdn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/*
|------------------------------------------------------------------------------
| CDN purge
|------------------------------------------------------------------------------
|
| The origin is authoritative and is already correct by the time any of this
| runs: the on-disk purge happens synchronously in the entry observer. So every
| assertion here is about the edge catching up without ever being able to hold
| up, or fail, a content save.
|
*/

function cloudflare(): CloudflarePurger
{
    return new CloudflarePurger(
        zoneId: 'zone-123',
        apiToken: 'token-abc',
        domain: 'https://chrisgarlick.com',
    );
}

it('resolves to a purger that does nothing when no driver is configured', function (): void {
    config()->set('cg-cms.cdn.driver', 'null');
    app()->forgetInstance(CdnPurger::class);

    expect(app(CdnPurger::class))->toBeInstanceOf(NullPurger::class);
});

it('falls back to null rather than throwing when the token is missing', function (): void {
    config()->set('cg-cms.cdn.driver', 'cloudflare');
    config()->set('cg-cms.cdn.cloudflare.zone_id', 'zone-123');
    config()->set('cg-cms.cdn.cloudflare.api_token', null);
    app()->forgetInstance(CdnPurger::class);

    // Misconfiguration must not be able to fail a content save.
    expect(app(CdnPurger::class))->toBeInstanceOf(NullPurger::class);
});

it('purges absolute URLs, because a relative path purges nothing', function (): void {
    Http::fake([
        'api.cloudflare.com/*' => Http::response(['success' => true], 200),
    ]);

    cloudflare()->purge(['/article/one', '/article/two']);

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://api.cloudflare.com/client/v4/zones/zone-123/purge_cache'
            && $request['files'] === [
                'https://chrisgarlick.com/article/one',
                'https://chrisgarlick.com/article/two',
            ];
    });
});

it('chunks at the API limit rather than losing the overflow', function (): void {
    Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true], 200)]);

    $paths = array_map(fn (int $n): string => "/article/{$n}", range(1, 70));

    $purged = cloudflare()->purge($paths);

    // 70 URLs is three calls of at most 30, not one call that silently drops 40.
    Http::assertSentCount(3);
    expect($purged)->toBe(70);
});

it('treats a 200 carrying success false as a failure', function (): void {
    Http::fake([
        'api.cloudflare.com/*' => Http::response([
            'success' => false,
            'errors' => [['code' => 1012, 'message' => 'Request must contain files']],
        ], 200),
    ]);

    expect(cloudflare()->purge(['/article/one']))->toBe(0);
});

it('never throws when the API is unreachable', function (): void {
    Http::fake(function (): void {
        throw new ConnectionException('Connection timed out');
    });

    // A save must survive Cloudflare being down.
    expect(cloudflare()->purge(['/article/one']))->toBe(0);
});

/*
| Dispatch, not execution. The save path hands off and returns.
*/

it('queues the purge from a content save rather than calling out inline', function (): void {
    config()->set('cg-cms.cdn.driver', 'cloudflare');
    Queue::fake();

    makeArticle('queued-purge');

    Queue::assertPushed(PurgeCdn::class);
});

it('queues nothing when there is no CDN in front of the origin', function (): void {
    config()->set('cg-cms.cdn.driver', 'null');
    Queue::fake();

    makeArticle('no-cdn');

    Queue::assertNotPushed(PurgeCdn::class);
});

it('gives up after two attempts instead of retrying into a queue backlog', function (): void {
    expect((new PurgeCdn(['/a']))->tries)->toBe(2);
});
