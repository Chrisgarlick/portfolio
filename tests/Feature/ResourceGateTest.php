<?php

declare(strict_types=1);

use App\Jobs\RenderResource;
use App\Mail\ResourceDelivery;
use App\Mail\ResourceLeadNotification;
use App\Models\OutboundEmail;
use App\Models\ResourceDownload;
use App\Models\ResourceLead;
use App\Support\LeadToken;
use Cg\Cms\Forms\FormGuard;
use Cg\Cms\Lint\Linter;
use Cg\Cms\Models\Entry;
use Cg\Cms\Models\FormSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/*
|------------------------------------------------------------------------------
| Gated resources (Phase 5)
|------------------------------------------------------------------------------
|
| An email for a download: the request, the signed thanks page, and the
| download by signed link or device cookie, with Typeset renders cached.
|
*/

beforeEach(function (): void {
    Storage::fake('local');
    config([
        'cg-cms.site.contact_email' => 'chris@example.com',
        'services.typeset.key' => 'test-key',
    ]);
});

function gatedResource(string $slug = 'prompt-library', array $data = []): Entry
{
    return Linter::without(fn () => Entry::query()->create([
        'collection' => 'resource',
        'slug' => $slug,
        'title' => 'The Prompt Library',
        'status' => 'published',
        'data' => [
            'summary' => 'Prompts for professional services.',
            'markdown_body' => "# The Prompt Library\n\nPrompts.",
            'layout_json' => '',
            'sector' => 'All',
            ...$data,
        ],
    ]));
}

/** A request as the gate form sends it, with valid guard fields. */
function requestDownload(array $overrides = []): TestResponse
{
    $guard = app(FormGuard::class)->hiddenFields('resource-gate');
    $guard[FormGuard::TIMESTAMP] = (string) (time() - 30);

    return test()->postJson('/api/resources/request', [
        ...$guard,
        'slug' => 'prompt-library',
        'email' => 'Reader@Example.com',
        'firstName' => 'Rae',
        'company' => null,
        'sector' => 'Legal',
        'marketingConsent' => false,
        ...$overrides,
    ]);
}

function signedThanks(ResourceLead $lead, string $slug = 'prompt-library', int $days = 7): string
{
    return URL::temporarySignedRoute('resources.thanks', now()->addDays($days), ['slug' => $slug, 'lead' => $lead->id]);
}

function signedDownload(ResourceLead $lead, string $format, string $slug = 'prompt-library'): string
{
    return URL::temporarySignedRoute('resources.download', now()->addDays(7), ['slug' => $slug, 'format' => $format, 'lead' => $lead->id]);
}

/*
| Request
*/

it('stores the lead, mirrors a submission, queues both emails and remembers the device', function (): void {
    Mail::fake();
    gatedResource();

    $response = requestDownload()->assertOk()->assertJson(['ok' => true]);

    expect($response->json('redirect'))->toStartWith('/resources/prompt-library/thanks?')
        ->toContain('signature=');

    $lead = ResourceLead::query()->sole();

    expect($lead->email)->toBe('reader@example.com')
        ->and($lead->first_name)->toBe('Rae')
        ->and($lead->sector)->toBe('Legal');

    expect(FormSubmission::query()->where('form', 'resource-gate')->sole()->context)->toBe('prompt-library');

    Mail::assertQueued(ResourceDelivery::class, fn ($mail) => $mail->hasTo('reader@example.com'));
    Mail::assertQueued(ResourceLeadNotification::class, fn ($mail) => $mail->hasTo('chris@example.com'));

    $response->assertCookie(LeadToken::COOKIE)->assertPlainCookie(LeadToken::FLAG_COOKIE, '1');

    $cookie = collect($response->headers->getCookies())->firstWhere(fn ($c) => $c->getName() === LeadToken::COOKIE);
    expect($cookie->isHttpOnly())->toBeTrue()
        ->and(LeadToken::verify($cookie->getValue()))->toBe($lead->id);
});

it('fills gaps on a returning lead but never downgrades consent or overwrites', function (): void {
    Mail::fake();
    gatedResource();

    requestDownload(['marketingConsent' => true, 'company' => null])->assertOk();
    requestDownload(['marketingConsent' => false, 'firstName' => 'Someone Else', 'company' => 'Acme'])->assertOk();

    $lead = ResourceLead::query()->sole();

    expect($lead->marketing_consent)->toBeTrue()
        ->and($lead->first_name)->toBe('Rae')
        ->and($lead->company)->toBe('Acme');
});

it('answers a bot as if it worked and stores nothing', function (): void {
    Mail::fake();
    gatedResource();

    requestDownload([FormGuard::HONEYPOT => 'http://spam.example'])
        ->assertOk()
        ->assertExactJson(['ok' => true]);

    expect(ResourceLead::query()->count())->toBe(0);
    Mail::assertNothingQueued();
});

it('refuses an invalid email or an unknown resource', function (): void {
    gatedResource();

    requestDownload(['email' => 'not-an-email'])->assertStatus(422);
    requestDownload(['slug' => 'no-such-resource'])->assertStatus(422);

    expect(ResourceLead::query()->count())->toBe(0);
});

it('logs both emails to outbound_email_log once each when they actually send', function (): void {
    gatedResource();

    requestDownload()->assertOk();

    expect(OutboundEmail::query()->pluck('template')->sort()->values()->all())
        ->toBe(['resource_delivery', 'resource_lead_notify'])
        ->and(OutboundEmail::query()->where('template', 'resource_delivery')->value('to_email'))->toBe('reader@example.com');
});

/*
| Thanks page
*/

it('shows signed download links behind a valid link, and is never cached', function (): void {
    gatedResource();
    $lead = ResourceLead::query()->create(['email' => 'r@example.com']);

    $response = $this->get(signedThanks($lead))
        ->assertOk()
        ->assertSee('Pick a format')
        ->assertHeaderMissing('X-CG-Cache')
        ->assertCookie(LeadToken::COOKIE);

    expect($response->getContent())->toContain('/api/resources/prompt-library/download?')
        ->toContain('signature=');

    expect(glob(public_path(config('cg-cms.page_cache.path').'/resources/prompt-library/thanks*')))->toBe([]);
});

it('refuses an unsigned, tampered or expired thanks link with a way to ask again', function (): void {
    gatedResource();
    $lead = ResourceLead::query()->create(['email' => 'r@example.com']);

    $this->get('/resources/prompt-library/thanks?lead='.$lead->id)->assertForbidden()->assertSee('Request it again');
    $this->get(str_replace('lead='.$lead->id, 'lead=00000000-0000-0000-0000-000000000000', signedThanks($lead)))->assertForbidden();

    $expired = signedThanks($lead);
    $this->travel(8)->days();
    $this->get($expired)->assertForbidden()->assertHeaderMissing('Set-Cookie');
});

/*
| Download
*/

it('serves the markdown by signed link and logs the download', function (): void {
    gatedResource();
    $lead = ResourceLead::query()->create(['email' => 'r@example.com']);

    $response = $this->get(signedDownload($lead, 'md'))->assertOk();

    expect($response->getContent())->toContain('# The Prompt Library')
        ->and($response->headers->get('Content-Disposition'))->toBe('attachment; filename="prompt-library.md"')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');

    expect(ResourceDownload::query()->sole()->only(['lead_id', 'format']))->toBe(['lead_id' => $lead->id, 'format' => 'md']);
});

it('accepts the device cookie instead of a signed link', function (): void {
    gatedResource();
    $lead = ResourceLead::query()->create(['email' => 'r@example.com']);

    $this->withUnencryptedCookie(LeadToken::COOKIE, LeadToken::sign($lead->id))
        ->get('/api/resources/prompt-library/download?format=md')
        ->assertOk();
});

it('refuses a download with neither, or with a forged or expired cookie', function (): void {
    gatedResource();
    $lead = ResourceLead::query()->create(['email' => 'r@example.com']);

    $this->get('/api/resources/prompt-library/download?format=md')->assertUnauthorized();

    $this->withUnencryptedCookie(LeadToken::COOKIE, LeadToken::sign($lead->id).'x')
        ->get('/api/resources/prompt-library/download?format=md')->assertUnauthorized();

    $this->withUnencryptedCookie(LeadToken::COOKIE, LeadToken::sign($lead->id, -1))
        ->get('/api/resources/prompt-library/download?format=md')->assertUnauthorized();

    expect(ResourceDownload::query()->count())->toBe(0);
});

it('renders a PDF once and serves the next request from the cache', function (): void {
    Queue::fake(); // Isolate the download path from the pre-render job.
    Http::fake(['*/api/render' => Http::response('%PDF-1.7 fake', 200)]);
    gatedResource('prompt-library', ['layout_json' => '{"blocks":[]}', 'typeset_client' => 'neon']);
    $lead = ResourceLead::query()->create(['email' => 'r@example.com']);

    $first = $this->get(signedDownload($lead, 'pdf'))->assertOk();
    $this->get(signedDownload($lead, 'pdf'))->assertOk();

    expect($first->getContent())->toBe('%PDF-1.7 fake')
        ->and($first->headers->get('Content-Type'))->toBe('application/pdf');

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request['input_format'] === 'json'
        && $request['client'] === 'neon'
        && $request['content'] === '{"blocks":[]}');

    expect(ResourceDownload::query()->count())->toBe(2);
});

it('serves a hand-authored DOCX from private storage instead of rendering', function (): void {
    Queue::fake();
    Http::fake();
    gatedResource();
    $lead = ResourceLead::query()->create(['email' => 'r@example.com']);
    Storage::disk('local')->put('private-resources/prompt-library/prompt-library.docx', 'DOCX-BYTES');

    $response = $this->get(signedDownload($lead, 'docx'))->assertOk();

    expect(file_get_contents($response->baseResponse->getFile()->getPathname()))->toBe('DOCX-BYTES');
    Http::assertNothingSent();
});

it('says HTML is coming soon and maps a Typeset failure to its status', function (): void {
    Http::fake(['*/api/render' => Http::response('boom', 500)]);
    gatedResource();
    $lead = ResourceLead::query()->create(['email' => 'r@example.com']);

    $this->get(signedDownload($lead, 'html'))->assertStatus(503)->assertJson(['error' => 'HTML rendering is coming soon.']);
    $this->get(signedDownload($lead, 'pdf'))->assertStatus(502);

    config(['services.typeset.key' => null]);
    $this->get(signedDownload($lead, 'docx'))->assertStatus(503);
});

/*
| Pre-rendering
*/

it('queues a pre-render when a published resource is saved, not a draft', function (): void {
    Queue::fake();

    $resource = gatedResource();
    Queue::assertPushed(RenderResource::class, fn ($job) => $job->entryId === $resource->id);

    Queue::fake();
    Linter::without(fn () => Entry::query()->create(['collection' => 'resource', 'slug' => 'draft-one', 'title' => 'Draft', 'status' => 'draft', 'data' => []]));
    Queue::assertNotPushed(RenderResource::class);
});

it('pre-renders on save, so the first download makes no Typeset call', function (): void {
    Http::fake(['*/api/render' => Http::response('%PDF-1.7 fake', 200)]);

    gatedResource(); // The sync queue runs RenderResource now: pdf and docx.
    Http::assertSentCount(2);

    $lead = ResourceLead::query()->create(['email' => 'r@example.com']);
    $this->get(signedDownload($lead, 'pdf'))->assertOk();
    $this->get(signedDownload($lead, 'docx'))->assertOk();

    Http::assertSentCount(2);
});

it('syncs layout JSON from disk, byte for byte, and reports invalid files', function (): void {
    gatedResource();
    $dir = storage_path('framework/testing/layouts-'.uniqid());
    @mkdir("{$dir}/prompt-library", 0777, true);
    @mkdir("{$dir}/broken", 0777, true);
    file_put_contents("{$dir}/prompt-library/prompt-library.json", '{ "title": "x — y" }');
    file_put_contents("{$dir}/broken/broken.json", '{ nope');

    $this->artisan('resources:sync-layouts', ['dir' => $dir])->assertFailed();

    expect(Entry::query()->where('slug', 'prompt-library')->value('data')['layout_json'])->toBe('{ "title": "x — y" }');

    exec('rm -rf '.escapeshellarg($dir));
});
