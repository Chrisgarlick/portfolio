<?php

declare(strict_types=1);

use App\Mail\DiagnosticHighFitNotification;
use Cg\Cms\Forms\FormGuard;
use Cg\Cms\Models\FormSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

/*
|------------------------------------------------------------------------------
| The fit check at /diagnostic, scored on the server
|------------------------------------------------------------------------------
*/

beforeEach(function (): void {
    Mail::fake();
    config(['cg-cms.site.contact_email' => 'chris@example.com']);
    RateLimiter::clear('diagnostic|127.0.0.1');
});

/** A submission that passes the form guard: real token, rendered long enough ago. */
function diagnosticAnswers(array $overrides = []): array
{
    $hidden = app(FormGuard::class)->hiddenFields('diagnostic');
    $hidden[FormGuard::TIMESTAMP] = (string) (time() - 30);

    return [...$hidden, ...[
        'businessType' => 'Agency',
        'task' => 'Assembling monthly client reports from three tools',
        'hours' => '10h+',
        'stack' => 'Notion, Xero',
        'priority' => 'All three',
        'email' => 'lead@example.com',
    ], ...$overrides];
}

it('scores on the server and ignores whatever the client claims', function (): void {
    $this->postJson('/api/diagnostic', diagnosticAnswers([
        'hours' => '<2h', 'stack' => '', 'priority' => 'Reduce time', 'task' => 'short',
        'fitScore' => '99', 'fitTier' => 'high',
    ]))->assertOk()->assertJson(['ok' => true, 'score' => 2, 'tier' => 'low']);

    expect(FormSubmission::query()->first()->data)->toMatchArray(['fitScore' => 2, 'fitTier' => 'low']);

    Mail::assertNothingQueued();
});

it('notifies the inbox about a high fit, and only a high fit', function (): void {
    $this->postJson('/api/diagnostic', diagnosticAnswers())->assertJson(['tier' => 'high']);

    Mail::assertQueued(DiagnosticHighFitNotification::class, fn ($mail) => $mail->hasTo('chris@example.com') && $mail->score === 7);

    $this->postJson('/api/diagnostic', diagnosticAnswers(['hours' => '2-10h', 'priority' => 'Reduce time']));

    Mail::assertQueuedCount(1);
});

it('mirrors every submission into the form submissions the admin lists', function (): void {
    $this->postJson('/api/diagnostic', diagnosticAnswers());

    $submission = FormSubmission::query()->firstOrFail();

    expect($submission->form)->toBe('diagnostic')
        ->and($submission->rejected_for)->toBeNull()
        ->and($submission->data['task'])->toBe('Assembling monthly client reports from three tools')
        ->and($submission->ip_hash)->toBe(sha1('127.0.0.1'));
});

it('still shows a bot-like submission its result but never notifies about it', function (): void {
    $this->postJson('/api/diagnostic', diagnosticAnswers([FormGuard::HONEYPOT => 'http://spam.example']))
        ->assertOk()->assertJson(['tier' => 'high']);

    expect(FormSubmission::query()->first()->rejected_for)->toBe('honeypot');
    Mail::assertNothingQueued();
});

it('rejects a submission missing a required answer, with the live message', function (): void {
    $this->postJson('/api/diagnostic', diagnosticAnswers(['hours' => '']))
        ->assertStatus(400)->assertJson(['error' => 'Missing required fields.']);

    $this->postJson('/api/diagnostic', diagnosticAnswers(['priority' => 'World domination']))->assertStatus(400);

    expect(FormSubmission::query()->count())->toBe(0);
});

it('serves the page bare, unindexed, cacheable and carrying the guard fields', function (): void {
    $this->get('/diagnostic')
        ->assertOk()
        ->assertSee('Is AI implementation worth doing for you right now?')
        ->assertSee('name="_form_token"', false)
        ->assertSee('noindex', false)
        ->assertDontSee('id="nav-toggle"', false)
        ->assertHeader('X-CG-Cache', 'MISS');
});
