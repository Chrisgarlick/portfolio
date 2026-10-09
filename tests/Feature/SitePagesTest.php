<?php

declare(strict_types=1);

use App\Mail\FormSubmissionNotification;
use Cg\Cms\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/*
|------------------------------------------------------------------------------
| Phase 4: the live site's pages
|------------------------------------------------------------------------------
|
| Routing and the behaviour around it, not the markup: that every live URL
| resolves, that a page with its own address is only served there, and that
| the static pages are cacheable. What the pages look like is checked by eye against the live site.
|
*/

function publish(string $collection, string $slug, string $title, array $data = []): Entry
{
    return Entry::query()->create([
        'collection' => $collection,
        'slug' => $slug,
        'title' => $title,
        'status' => 'published',
        'published_at' => now()->subDay(),
        'data' => $data,
    ]);
}

it('serves every collection listing and detail page', function (string $collection, string $listing, string $detail, array $data): void {
    publish($collection, 'the-entry', 'The entry title for this page', $data);

    $this->get($listing)->assertOk()->assertSee('The entry title for this page');
    $this->get($detail)->assertOk()->assertSee('The entry title for this page');
})->with([
    'project' => ['project', '/work', '/work/the-entry', ['disclosure' => 'named', 'summary' => 'Built it.']],
    'tool' => ['tool', '/tools', '/tools/the-entry', ['category' => 'SEO', 'description' => 'Checks things.']],
    'resource' => ['resource', '/resources', '/resources/the-entry', ['sector' => 'Legal', 'summary' => 'A guide.']],
    'article' => ['article', '/article', '/article/the-entry', ['excerpt' => 'x', 'body' => tiptapParagraph('Body.')]],
]);

it('404s a detail page with no entry behind it', function (string $url): void {
    $this->get($url)->assertNotFound();
})->with(['/work/nothing', '/tools/nothing', '/resources/nothing', '/article/nothing', '/for/nothing']);

it('keeps a confidential client name off /work', function (): void {
    publish('project', 'nda-work', 'Confidential work', ['disclosure' => 'anonymised', 'client_name' => 'Secret Client Ltd', 'client_descriptor' => 'a UK law firm']);

    $this->get('/work')->assertOk()->assertSee('Confidential work')->assertDontSee('Secret Client Ltd');
});

it('serves a block-built page at its own address and redirects /page/ to it', function (): void {
    publish('page', 'about', 'About', ['content' => [['type' => 'hero', 'data' => ['heading' => 'One person.']]]]);

    $this->get('/about')->assertOk()->assertSee('One person.');
    $this->get('/page/about')->assertRedirect('/about')->assertStatus(301);
});

it('404s a page address whose page does not exist yet', function (): void {
    $this->get('/about')->assertNotFound();
    $this->get('/industries/ai-for-law-firms')->assertNotFound();
});

it('only serves industry and service pages from their own section', function (): void {
    publish('page', 'ai-agents', 'Custom AI Agents', ['content' => [['type' => 'hero', 'data' => ['heading' => 'Agents']]]]);

    $this->get('/services/ai-agents')->assertOk();

    // A service page is not an industry page, whatever its slug.
    $this->get('/industries/ai-agents')->assertNotFound();
});

it('falls back to the service collection for a slug that is not a service page', function (): void {
    publish('service', 'website-building', 'Website building', ['summary' => 'Sites that load fast.']);

    $this->get('/services/website-building')->assertOk()->assertSee('Website building');
});

it('renders the /for pages from config and 404s unknown audiences', function (): void {
    $this->get('/for')->assertOk()->assertSee('Consultants');

    $this->get('/for/consultants')->assertOk()
        ->assertSee(config('for-pages.pages.consultants.headline'))
        ->assertSee('/resources/'.config('for-pages.pages.consultants.resource_slug'));

    $this->get('/for/astronauts')->assertNotFound();
});

it('page-caches the static pages', function (string $url): void {
    $this->get($url)->assertOk()->assertHeader('X-CG-Cache', 'MISS');
})->with(['/privacy', '/terms', '/for', '/for/freelancers']);

it('does not double the site name on titles the live site already suffixed', function (): void {
    $html = (string) $this->get('/privacy')->getContent();

    expect($html)->toContain('<title>Privacy Policy | Chris Garlick</title>');
});

it('renders a working resource gate, with guard fields and the picker ready to swap in', function (): void {
    publish('resource', 'gated', 'A gated resource', ['summary' => 'Downloadable.']);

    $html = (string) $this->get('/resources/gated')->getContent();

    // The page is cached and identical for everyone, so it carries both the
    // gate and the (hidden) format picker; the cookie flag picks one in JS.
    expect($html)->toContain('id="resource-form"')
        ->toContain('action="/api/resources/request"')
        ->toContain('name="_form_token"')
        ->toContain('id="picker-container"')
        ->not->toMatch('/<button type="submit" disabled/');
});

it('groups the article listing by year and shows a read time on the article', function (): void {
    publish('article', 'this-year', 'Written this year', ['excerpt' => 'x', 'body' => tiptapParagraph(str_repeat('word ', 450))]);
    Entry::query()->where('slug', 'this-year')->update(['published_at' => now()->setDate(2026, 3, 1)]);
    publish('article', 'last-year', 'Written last year', ['excerpt' => 'x', 'body' => tiptapParagraph('Short.')]);
    Entry::query()->where('slug', 'last-year')->update(['published_at' => now()->setDate(2025, 3, 1)]);

    $this->get('/article')->assertOk()->assertSeeInOrder(['2026', 'Written this year', '2025', 'Written last year']);

    $this->get('/article/this-year')->assertOk()->assertSee('3 min read');
});

it('answers a form submitted from script with JSON', function (): void {
    $this->postJson('/forms/contact', [
        'name' => 'A visitor',
        'email' => 'visitor@example.com',
        'company' => 'A small firm',
        'project_type' => 'Laravel',
        'message' => 'Our booking system needs rebuilding before the summer.',
    ])->assertOk()->assertJson(['message' => config('cg-forms.contact.success')]);
});

it('queues the enquiry email to the contact address instead of sending it in the request', function (): void {
    Mail::fake();
    config(['cg-forms.contact.notify' => 'owner@example.com']);

    $this->postJson('/forms/contact', [
        'name' => 'A visitor',
        'email' => 'visitor@example.com',
        'message' => 'Our booking system needs rebuilding before the summer.',
    ])->assertOk();

    Mail::assertQueued(
        FormSubmissionNotification::class,
        fn (FormSubmissionNotification $mail): bool => $mail->hasTo('owner@example.com') && $mail->hasReplyTo('visitor@example.com'),
    );
    Mail::assertNothingSent();
});

it('gives the script field errors it can place, not a redirect', function (): void {
    // The page has no session, so a redirect back would lose the errors.
    $this->postJson('/forms/contact', ['name' => 'A visitor', 'project_type' => 'Shipping'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'project_type', 'message']);
});

it('renders the 404 page in the site layout', function (): void {
    $this->get('/no-such-page')->assertNotFound()->assertSee('Page not found');
});
