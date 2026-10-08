<?php

declare(strict_types=1);

use App\Content\ProjectPresenter;
use Cg\Cms\Cache\PageCache;
use Cg\Cms\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeProject(array $data, string $slug = 'a-project'): Entry
{
    return Entry::query()->create([
        'collection' => 'project',
        'slug' => $slug,
        'title' => $data['title'] ?? 'A project',
        'status' => 'published',
        'published_at' => now()->subDay(),
        'data' => array_replace([
            'summary' => 'A short summary of the work.',
            'body' => tiptapParagraph('What the work involved.'),
            'kind' => 'client',
            'disclosure' => 'named',
        ], $data),
    ]);
}

/*
|------------------------------------------------------------------------------
| NDA enforcement
|------------------------------------------------------------------------------
|
| The tests that matter most in this file. Client work is under NDA to varying
| degrees, and "remember not to mention the client" is a discipline that fails
| eventually. These assert the system enforces it instead.
|
| Note the client name is deliberately still STORED when undisclosed: you want
| your own record of who the work was for. The guarantee is that it never
| reaches the HTML.
|
*/

it('never renders the client name when disclosure is undisclosed', function (): void {
    makeProject([
        'title' => 'A confidential build',
        'disclosure' => 'undisclosed',
        'client_name' => 'Hogwarts Legal LLP',
        'client_descriptor' => 'a UK law firm',
    ], 'confidential-build');

    $response = $this->get('/work/confidential-build')->assertOk();

    $response->assertDontSee('Hogwarts Legal LLP');
    // Not even the descriptor: undisclosed means the client is not acknowledged.
    $response->assertDontSee('a UK law firm');

    // The work itself is still shown.
    $response->assertSee('A confidential build');
});

it('renders the descriptor but never the name when anonymised', function (): void {
    makeProject([
        'title' => 'An anonymised build',
        'disclosure' => 'anonymised',
        'client_name' => 'Hogwarts Legal LLP',
        'client_descriptor' => 'a UK law firm',
    ], 'anonymised-build');

    $this->get('/work/anonymised-build')
        ->assertOk()
        ->assertSee('a UK law firm')
        ->assertDontSee('Hogwarts Legal LLP');
});

it('renders the name when disclosure is named', function (): void {
    makeProject([
        'disclosure' => 'named',
        'client_name' => 'Acme Ltd',
    ], 'named-build');

    $this->get('/work/named-build')->assertOk()->assertSee('Acme Ltd');
});

it('suppresses links for anything not fully disclosed', function (): void {
    // A live URL identifies a client as surely as their name does, and a repo
    // link more so. Anonymising the name while linking the site is pointless.
    foreach (['anonymised', 'undisclosed'] as $i => $disclosure) {
        makeProject([
            'disclosure' => $disclosure,
            'client_name' => 'Acme Ltd',
            'live_url' => 'https://acme-legal.example.com',
            'repo_url' => 'https://github.com/acme/private',
        ], "gated-links-{$i}");

        $this->get("/work/gated-links-{$i}")
            ->assertOk()
            ->assertDontSee('acme-legal.example.com')
            ->assertDontSee('github.com/acme/private');
    }
});

it('shows links when disclosure is named', function (): void {
    makeProject([
        'disclosure' => 'named',
        'live_url' => 'https://example.com',
    ], 'open-links');

    $this->get('/work/open-links')->assertOk()->assertSee('example.com');
});

it('keeps the client name out of the cached listing too', function (): void {
    // The presenter runs before the cache, so an undisclosed name is never
    // written into a cache file in the first place.
    makeProject([
        'disclosure' => 'undisclosed',
        'client_name' => 'Secret Client Ltd',
    ], 'listed-project');

    $this->get('/work')->assertOk()->assertDontSee('Secret Client Ltd');

    $file = app(PageCache::class)->fileFor('/work');

    expect(file_exists($file))->toBeTrue();
    expect(file_get_contents($file))->not->toContain('Secret Client Ltd');
});

it('still stores the client name for your own records', function (): void {
    $project = makeProject([
        'disclosure' => 'undisclosed',
        'client_name' => 'Secret Client Ltd',
    ]);

    expect($project->value('client_name'))->toBe('Secret Client Ltd');
    expect(ProjectPresenter::make($project)->clientLabel())->toBeNull();
});

it('reports null rather than an empty label when there is nothing sayable', function (): void {
    // A template must branch, not print a blank line where a client would be.
    $anonymousWithNoDescriptor = makeProject([
        'disclosure' => 'anonymised',
        'client_name' => 'Acme',
        'client_descriptor' => '   ',
    ]);

    expect(ProjectPresenter::make($anonymousWithNoDescriptor)->clientLabel())->toBeNull();
});

/*
|------------------------------------------------------------------------------
| Presentation
|------------------------------------------------------------------------------
*/

it('splits a comma-separated stack into a list', function (): void {
    $project = makeProject(['stack' => 'Laravel, Postgres , Claude,  Astro ']);

    expect(ProjectPresenter::make($project)->stack())
        ->toBe(['Laravel', 'Postgres', 'Claude', 'Astro']);
});

it('returns an empty stack rather than a blank entry', function (): void {
    expect(ProjectPresenter::make(makeProject(['stack' => '']))->stack())->toBe([]);
});

it('keeps the live /work URLs', function (): void {
    // These already resolve on the live site, so nothing needs redirecting.
    makeProject([], 'url-parity');

    $this->get('/work')->assertOk();
    $this->get('/work/url-parity')->assertOk();
});
