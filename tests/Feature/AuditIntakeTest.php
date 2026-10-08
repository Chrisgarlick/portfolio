<?php

declare(strict_types=1);

use App\Mail\AuditAcknowledgement;
use App\Mail\AuditRequestNotification;
use App\Models\AuditSubmission;
use App\Models\OutboundEmail;
use App\Support\AuditRefs;
use Cg\Cms\Forms\FormGuard;
use Cg\Cms\Models\FormSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/*
|------------------------------------------------------------------------------
| The AI readiness audit request (/audit, POST /api/audit/submit)
|------------------------------------------------------------------------------
*/

beforeEach(function (): void {
    config(['cg-cms.site.contact_email' => 'owner@example.com']);
});

/** A valid request, with the guard's hidden fields as a rendered page would carry them. */
function auditPayload(array $overrides = []): array
{
    $hidden = app(FormGuard::class)->hiddenFields('audit-intake');
    $hidden[FormGuard::TIMESTAMP] = (string) (time() - 30);

    return array_replace([
        ...$hidden,
        'name' => 'Ada Lovelace',
        'email' => 'Ada@Example.COM',
        'companyName' => 'Analytical Engines Ltd',
        'website' => 'https://example.com',
        'sector' => 'law-firm',
        'monthlyMatters' => '10-30',
        'bottleneckAreas' => ['Client intake', 'Billing'],
        'teamSize' => '6-15',
        'biggestBottleneck' => str_repeat('Retyping client intake forms into the case system. ', 2),
        'budgetRange' => '£2-5k',
    ], $overrides);
}

function submitAudit(array $overrides = []): TestResponse
{
    return test()->postJson('/api/audit/submit', auditPayload($overrides));
}

it('renders the form from the YAML, with every sector block and the guard fields', function (): void {
    // Cached like any static page: the guard token is valid for 30 days.
    $html = $this->get('/audit')->assertOk()->assertHeader('X-CG-Cache', 'MISS')->getContent();

    expect($html)
        ->toContain('Tell me about your business. Get a costed AI plan back.')
        ->toContain('data-sector-block="law-firm"')
        ->toContain('data-sector-block="architecture"')
        ->toContain('name="_form_token"')
        ->toContain('Your biggest manual bottleneck right now');
});

it('stores a request, allocates the first reference of the year and answers in the live shape', function (): void {
    Mail::fake();

    $response = submitAudit()->assertOk();

    $ref = 'CG-'.now()->year.'-001';
    expect($response->json())->toBe(['ok' => true, 'auditRef' => $ref]);

    $submission = AuditSubmission::query()->firstOrFail();

    expect($submission->audit_ref)->toBe($ref)
        ->and($submission->email)->toBe('ada@example.com')
        ->and($submission->status)->toBe('submitted')
        ->and($submission->privacy_notice_version)->toBe('2026-05-14')
        ->and($submission->data['bottleneckAreas'])->toBe(['Client intake', 'Billing'])
        // The guard's own fields are not part of the request.
        ->and($submission->data)->not->toHaveKeys(['_form_token', FormGuard::TIMESTAMP]);

    submitAudit(['email' => 'second@example.com']);
    expect(AuditSubmission::query()->latest('submitted_at')->orderByDesc('audit_ref')->value('audit_ref'))
        ->toBe('CG-'.now()->year.'-002');
});

it('takes the next number when another request wins the race for one', function (): void {
    Mail::fake();

    $year = now()->year;
    AuditSubmission::query()->create([
        'audit_ref' => "CG-{$year}-001", 'email' => 'first@example.com', 'data' => [],
        'privacy_notice_version' => 'x', 'submitted_at' => now(),
    ]);

    // The losing side of the race: it read the table before the winner's
    // insert landed, so its first answer is a number that is already taken.
    app()->instance(AuditRefs::class, new class extends AuditRefs
    {
        private bool $first = true;

        public function next(): string
        {
            if ($this->first) {
                $this->first = false;

                return 'CG-'.now()->year.'-001';
            }

            return parent::next();
        }
    });

    submitAudit()->assertOk()->assertJson(['auditRef' => "CG-{$year}-002"]);
});

it('rejects what the live endpoint rejected, with the same messages', function (array $overrides, string $message): void {
    submitAudit($overrides)->assertStatus(400)->assertExactJson(['error' => $message]);

    expect(AuditSubmission::query()->count())->toBe(0);
})->with([
    'missing name' => [['name' => ''], 'Missing required field: name'],
    'missing team size' => [['teamSize' => null], 'Missing required field: teamSize'],
    'bad email' => [['email' => 'not-an-email'], 'Please enter a valid email address.'],
    'bad website' => [['website' => 'example.com'], 'Please enter a valid website URL.'],
    'unknown sector' => [['sector' => 'shipping'], 'Invalid sector selection.'],
    'short bottleneck' => [['biggestBottleneck' => 'Too short.'], 'Please give a bit more detail on your biggest bottleneck (50+ characters).'],
]);

it('mirrors the request into form submissions for the admin', function (): void {
    Mail::fake();

    submitAudit();

    $mirror = FormSubmission::query()->where('form', 'audit-intake')->firstOrFail();

    expect($mirror->data['auditRef'])->toBe('CG-'.now()->year.'-001')
        ->and($mirror->data['companyName'])->toBe('Analytical Engines Ltd')
        ->and($mirror->rejected_for)->toBeNull()
        ->and($mirror->notified_at)->not->toBeNull();
});

it('queues the acknowledgement and the internal notification, each tied to the submission', function (): void {
    Mail::fake();

    submitAudit();
    $submission = AuditSubmission::query()->firstOrFail();

    Mail::assertQueued(AuditAcknowledgement::class, fn ($mail) => $mail->hasTo('ada@example.com')
        && $mail->submission->is($submission)
        && str_contains($mail->envelope()->subject, $submission->audit_ref));

    Mail::assertQueued(AuditRequestNotification::class, fn ($mail) => $mail->hasTo('owner@example.com')
        && $mail->envelope()->replyTo[0]->address === 'ada@example.com');
});

it('logs each email once it is actually sent, with its template and submission', function (): void {
    submitAudit();
    $submission = AuditSubmission::query()->firstOrFail();

    $log = OutboundEmail::query()->orderBy('template')->get();

    expect($log)->toHaveCount(2)
        ->and($log->pluck('template')->all())->toBe(['audit_acknowledgement', 'audit_internal_notify'])
        ->and($log->pluck('audit_submission_id')->unique()->all())->toBe([$submission->id])
        ->and($log->firstWhere('template', 'audit_acknowledgement')->to_email)->toBe('ada@example.com');
});

it('answers a bot as a success, stores nothing and records the rejection', function (): void {
    Mail::fake();

    submitAudit([FormGuard::HONEYPOT => 'http://spam.example'])->assertOk()->assertExactJson(['ok' => true]);

    expect(AuditSubmission::query()->count())->toBe(0)
        ->and(FormSubmission::query()->where('form', 'audit-intake')->value('rejected_for'))->toBe('honeypot');

    Mail::assertNothingQueued();
});

it('still stores the request when no contact address is configured, but does not mark it notified', function (): void {
    Mail::fake();
    config(['cg-cms.site.contact_email' => null]);

    submitAudit()->assertOk();

    Mail::assertQueued(AuditAcknowledgement::class);
    Mail::assertNotQueued(AuditRequestNotification::class);

    expect(FormSubmission::query()->where('form', 'audit-intake')->value('notified_at'))->toBeNull();
});
