<?php

declare(strict_types=1);

use App\Console\Commands\ConsolidateSiteCommand;
use App\Mail\DataDeletionLink;
use App\Mail\FormSubmissionNotification;
use App\Models\GdprDeletion;
use Cg\Cms\Forms\FormGuard;
use Cg\Cms\Models\FormSubmission;
use Cg\Cms\Models\Redirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/*
| The /start brief and the /contact form: kit pages whose forms post to their
| own address and work without JavaScript, store the answers and where the
| visitor came from, tell Chris, and are found by the self-serve deletion.
*/

beforeEach(function (): void {
    // Just these pages, as site:install-content --publish installs each one
    // (the full install also brings every page's images, which is slow).
    $command = app(App\Console\Commands\InstallContentCommand::class);
    $values = new ReflectionMethod($command, 'fileValues');
    $content = app(Cg\Cms\Content\ContentService::class);

    foreach (['start', 'contact', 'thanks'] as $slug) {
        $input = $values->invoke($command, database_path("content/pages/{$slug}.json"), 'page', app(Cg\Cms\Schema\CollectionRegistry::class));
        $content->createDraft('page', $input);
        $content->publish(Cg\Cms\Models\Entry::query()->collection('page')->where('slug', $slug)->firstOrFail());
    }

    config(['cg-forms.start.notify' => 'owner@example.com', 'cg-forms.contact.notify' => 'owner@example.com']);
});

function startBrief(array $overrides = []): array
{
    $guard = app(FormGuard::class)->hiddenFields('start');
    $guard[FormGuard::TIMESTAMP] = (string) (time() - 30);

    return [
        ...$guard,
        'need' => 'A Laravel app or tool',
        'details' => 'Our booking system needs rebuilding before the summer.',
        'budget' => 'Medium',
        'timeline' => 'In the next month or two',
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'company' => 'Analytical Engines Ltd',
        'website' => 'analytical.example',
        'source' => ['landing' => '/start?utm_source=linkedin', 'referrer' => 'https://www.linkedin.com/', 'utm_source' => 'linkedin', 'utm_medium' => ''],
        ...$overrides,
    ];
}

function contactMessage(array $overrides = []): array
{
    $guard = app(FormGuard::class)->hiddenFields('contact');
    $guard[FormGuard::TIMESTAMP] = (string) (time() - 30);

    return [...$guard, 'name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'message' => 'Loved the article on page caching.', ...$overrides];
}

it('serves /start as a kit page with the three fieldsets, instead of redirecting', function (): void {
    $this->get('/start')
        ->assertOk()
        ->assertSee('data-mode="night"', false)
        ->assertSee('<form class="site-form" method="POST" action="/start"', false)
        ->assertSee('name="_form_token"', false)
        ->assertSee('name="website_url"', false)
        ->assertSeeInOrder(['Step 01 of 03', 'Step 02 of 03', 'Step 03 of 03'])
        ->assertSee('<legend class="kit-field-label">', false)
        ->assertSee('type="radio" name="need" value="Not sure yet"', false)
        ->assertSee('<label class="kit-field-label" for="start-email">', false)
        ->assertSee('href="/privacy"', false)
        ->assertDontSee('£')
        ->assertHeader('X-CG-Cache', 'MISS');
});

it('stores a brief with its answers and source, tells Chris, and lands on the thanks page', function (): void {
    Mail::fake();

    // A plain form post, as a browser without JavaScript sends it.
    $this->post('/start', startBrief())->assertRedirect('/start/thanks');

    $submission = FormSubmission::query()->where('form', 'start')->sole();

    expect($submission->rejected_for)->toBeNull()
        ->and($submission->data)->toMatchArray([
            'need' => 'A Laravel app or tool',
            'budget' => 'Medium',
            'timeline' => 'In the next month or two',
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'company' => 'Analytical Engines Ltd',
            'website' => 'analytical.example',
        ])
        ->and($submission->data['source'])->toBe(['landing' => '/start?utm_source=linkedin', 'referrer' => 'https://www.linkedin.com/', 'utm_source' => 'linkedin'])
        ->and($submission->notified_at)->not->toBeNull();

    Mail::assertQueued(FormSubmissionNotification::class, fn (FormSubmissionNotification $mail): bool => $mail->hasTo('owner@example.com')
        && $mail->hasReplyTo('ada@example.com')
        && $mail->envelope()->subject === 'New project brief');

    $this->get('/start/thanks')->assertOk()->assertSee('48 hours');
});

it('takes a brief with only the required answers', function (): void {
    Mail::fake();

    $this->post('/start', startBrief(['budget' => null, 'timeline' => null, 'company' => null, 'website' => null, 'source' => null]))
        ->assertRedirect('/start/thanks');

    expect(FormSubmission::query()->where('form', 'start')->sole()->data)->not->toHaveKey('source');
});

it('answers a brief with problems with the page, errors in words, and the answers kept', function (): void {
    Mail::fake();

    $response = $this->post('/start', startBrief(['need' => null, 'details' => '', 'email' => 'not-an-address', 'budget' => '£5,000']));

    $response->assertStatus(422)
        ->assertSee('data-mode="night"', false)
        ->assertSee('role="alert"', false)
        ->assertSee('4 things need')
        ->assertSee('<a href="#start-need-0">Pick the closest kind of work. Not sure yet is fine.</a>', false)
        ->assertSee('<p class="kit-field-error" id="start-email-error">That email address does not look right. Check it for typos.</p>', false)
        ->assertSee('aria-describedby="start-email-error"', false)
        ->assertSee('aria-invalid="true"', false)
        ->assertSee('Pick one of the budget bands, or leave it.')
        ->assertSee('value="Ada Lovelace"', false)
        ->assertSee('value="not-an-address"', false)
        ->assertSee('value="In the next month or two" id="start-timeline-1"'."\n".'                    checked', false);

    expect(FormSubmission::query()->where('form', 'start')->sole()->rejected_for)->toBe('invalid');
    Mail::assertNothingQueued();
});

it('gives a script its errors as JSON and where to go on success', function (): void {
    Mail::fake();

    $this->postJson('/start', startBrief(['name' => '']))->assertStatus(422)->assertJsonValidationErrors(['name' => 'Tell me your name.']);
    $this->postJson('/start', startBrief())->assertOk()->assertJson(['redirect' => '/start/thanks']);
});

it('quietly drops a bot: stored as rejected, nothing sent, the same thanks', function (array $bot): void {
    Mail::fake();

    $this->post('/start', startBrief($bot))->assertRedirect('/start/thanks');

    expect(FormSubmission::query()->where('form', 'start')->sole()->rejected_for)->not->toBeNull();
    Mail::assertNothingQueued();
})->with([
    'honeypot' => [[FormGuard::HONEYPOT => 'https://spam.example']],
    'no token' => [['_form_token' => 'forged']],
    // Rendered "in the future": datasets are built before the test runs.
    'too fast' => [[FormGuard::TIMESTAMP => (string) (time() + 3600)]],
]);

it('no longer lets the imported /start redirect win once the consolidation runs', function (): void {
    Redirect::query()->create(['from' => '/start', 'to' => '/tools/site-audit', 'status' => 301, 'match_type' => 'exact', 'force' => true]);

    $this->get('/start')->assertRedirect('/tools/site-audit');

    $this->artisan('site:consolidate', ['--skip-checks' => true])->assertSuccessful();

    expect(Redirect::query()->where('from', '/start')->exists())->toBeFalse()
        ->and(ConsolidateSiteCommand::REMOVE_REDIRECTS)->toContain('/start');
    $this->get('/start')->assertOk()->assertSee('action="/start"', false);
});

it('serves /contact as a kit page with the message form and a pointer to /start', function (): void {
    $this->get('/contact')
        ->assertOk()
        ->assertSee('data-mode="night"', false)
        ->assertSee('<header class="kit-section-header"', false)
        ->assertSee('<form class="site-form" method="POST" action="/contact"', false)
        ->assertSee('for="contact-name"', false)
        ->assertSee('for="contact-email"', false)
        ->assertSee('for="contact-message"', false)
        ->assertSee('48 hours')
        ->assertSee('href="/start"', false);
});

it('sends a contact message without JavaScript and shows the thanks on the page', function (): void {
    Mail::fake();

    $this->post('/contact', contactMessage())->assertOk()->assertSee('role="status"', false)->assertSee('reply within 48 hours')->assertDontSee('<form class="site-form"', false);

    expect(FormSubmission::query()->where('form', 'contact')->sole()->data['message'])->toBe('Loved the article on page caching.');
    Mail::assertQueued(FormSubmissionNotification::class, fn ($mail): bool => $mail->hasTo('owner@example.com'));

    $this->post('/contact', contactMessage(['message' => '']))->assertStatus(422)->assertSee('Write a line or two so I know what it is about.')->assertSee('value="Ada Lovelace"', false);
});

it('puts Contact beside Start a project in every kit footer', function (): void {
    foreach (glob(database_path('content/{pages,services,tools,work}/*.json'), GLOB_BRACE) as $file) {
        $json = (string) file_get_contents($file);

        if (! str_contains($json, '"kit-footer"')) {
            continue;
        }

        expect(preg_match('/"label": "Start a project",\s*"url": "\/start"\s*\},\s*\{\s*"label": "Contact",\s*"url": "\/contact"/', $json))->toBe(1, basename($file));
    }
});

it('finds briefs and contact messages in the self-serve deletion and erases them', function (): void {
    Mail::fake();
    $this->post('/start', startBrief())->assertRedirect();
    $this->post('/contact', contactMessage(['email' => 'ADA@example.com']))->assertOk();
    $this->post('/contact', contactMessage(['email' => 'someone-else@example.com']))->assertOk();

    requestDeletion()->assertOk()->assertExactJson(['ok' => true]);

    $link = null;
    Mail::assertQueued(DataDeletionLink::class, function (DataDeletionLink $mail) use (&$link): bool {
        $link = $mail->url;

        return $mail->hasTo('ada@example.com') && $mail->submission === null && $mail->messages === 2;
    });

    $this->get($link)->assertOk()->assertSee('Project brief')->assertSee('Contact')->assertSee('Yes, delete my data');
    expect(FormSubmission::query()->count())->toBe(3);

    $this->post('/api/data/delete', ['link' => $link])->assertOk()->assertSee('Your data has been removed.');

    expect(FormSubmission::query()->pluck('data')->pluck('email')->all())->toBe(['someone-else@example.com'])
        ->and(GdprDeletion::query()->sole()->email_hash)->toBe(hash('sha256', 'ada@example.com'));

    $this->postJson('/api/data/delete', ['link' => $link])->assertOk()->assertJson(['alreadyDeleted' => true]);
    $this->get($link)->assertOk()->assertSee('already been deleted');
});
