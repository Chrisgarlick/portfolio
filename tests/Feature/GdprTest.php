<?php

declare(strict_types=1);

use App\Models\AuditSubmission;
use App\Models\GdprDeletion;
use App\Models\OutboundEmail;
use App\Models\ResourceDownload;
use App\Models\ResourceLead;
use Cg\Cms\Models\FormSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

/*
|------------------------------------------------------------------------------
| GDPR: self-serve deletion, export, erasure and the retention sweep
|------------------------------------------------------------------------------
*/

beforeEach(function (): void {
    Storage::fake('local');
});

function gdprSubmission(array $overrides = []): AuditSubmission
{
    static $n = 0;
    $n++;

    $ref = 'CG-'.now()->year.'-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT);

    $submission = AuditSubmission::query()->create(array_replace([
        'audit_ref' => $ref,
        'email' => 'ada@example.com',
        'data' => ['name' => 'Ada Lovelace', 'companyName' => 'Analytical Engines Ltd', 'email' => 'ada@example.com'],
        'status' => 'submitted',
        'ip_address' => '203.0.113.9',
        'user_agent' => 'Test',
        'privacy_notice_version' => '2026-05-14',
        'submitted_at' => now(),
    ], $overrides));

    FormSubmission::query()->create([
        'form' => 'audit-intake',
        'context' => $submission->audit_ref,
        'data' => ['email' => $submission->email, 'auditRef' => $submission->audit_ref],
    ]);

    OutboundEmail::query()->create([
        'audit_submission_id' => $submission->id,
        'to_email' => $submission->email,
        'subject' => 'Your audit',
        'template' => 'audit_acknowledgement',
    ]);

    return $submission;
}

function deleteLink(AuditSubmission $submission): string
{
    return URL::signedRoute('data-delete', ['submission' => $submission->id]);
}

/*
| The self-serve link
*/

it('shows what would be deleted, and deletes nothing on a GET', function (): void {
    $submission = gdprSubmission(['pdf_path' => 'audits/report.pdf']);
    Storage::disk('local')->put('audits/report.pdf', 'pdf');

    $response = $this->get(deleteLink($submission))->assertOk();

    expect($response->getContent())
        ->toContain($submission->audit_ref)
        ->toContain('ada@example.com')
        ->toContain('Yes, delete my data')
        ->toContain('noindex');

    // Link scanners fetch every URL in an email. Opening it must be harmless.
    expect($submission->fresh()->isDeleted())->toBeFalse();
    Storage::disk('local')->assertExists('audits/report.pdf');
    expect($response->headers->get('X-CG-Cache'))->toBeNull();
});

it('refuses an unsigned or tampered link', function (): void {
    $submission = gdprSubmission();
    $other = gdprSubmission(['email' => 'other@example.com']);

    $this->get('/data/delete?submission='.$submission->id)->assertForbidden()->assertSee('This link is invalid');

    $tampered = str_replace($submission->id, $other->id, deleteLink($submission));
    $this->get($tampered)->assertForbidden();

    $this->postJson('/api/data/delete', ['link' => $tampered])->assertStatus(401)->assertJson(['error' => 'Invalid or expired link.']);
    $this->postJson('/api/data/delete', ['link' => ''])->assertStatus(401);

    expect($other->fresh()->isDeleted())->toBeFalse();
});

it('will not accept a signed link to some other route', function (): void {
    $submission = gdprSubmission();
    $foreign = URL::signedRoute('audit', ['submission' => $submission->id]);

    $this->postJson('/api/data/delete', ['link' => $foreign])->assertStatus(401);
});

it('redacts on confirm, as the live SQL did, and logs the deletion by hash', function (): void {
    $submission = gdprSubmission(['pdf_path' => 'audits/report.pdf']);
    Storage::disk('local')->put('audits/report.pdf', 'pdf');

    $this->postJson('/api/data/delete', ['link' => deleteLink($submission)])
        ->assertOk()
        ->assertExactJson(['ok' => true, 'alreadyDeleted' => false]);

    $submission->refresh();

    expect($submission->email)->toBe('redacted@gdpr.local')
        ->and($submission->data)->toBe([])
        ->and($submission->ip_address)->toBeNull()
        ->and($submission->user_agent)->toBeNull()
        ->and($submission->pdf_path)->toBeNull()
        ->and($submission->deletion_reason)->toBe('self-serve via /data/delete')
        ->and($submission->deleted_at)->not->toBeNull();

    Storage::disk('local')->assertMissing('audits/report.pdf');

    // The page promises all associated personal data: the mirror and the
    // email log held the address too.
    expect(FormSubmission::query()->where('form', 'audit-intake')->count())->toBe(0)
        ->and(OutboundEmail::query()->count())->toBe(0);

    $log = GdprDeletion::query()->sole();
    expect($log->email_hash)->toBe(hash('sha256', 'ada@example.com'))
        ->and($log->audit_ref)->toBe($submission->audit_ref)
        ->and($log->counts)->toMatchArray(['pdf' => 1, 'form_submissions' => 1, 'outbound_emails' => 1]);
});

it('is idempotent: a second confirm changes nothing and says so', function (): void {
    $submission = gdprSubmission();
    $link = deleteLink($submission);

    $this->postJson('/api/data/delete', ['link' => $link])->assertOk();
    $this->postJson('/api/data/delete', ['link' => $link])->assertOk()->assertJson(['alreadyDeleted' => true]);

    expect(GdprDeletion::query()->count())->toBe(1);

    $this->get($link)->assertOk()->assertSee('Your data has already been deleted.');
});

it('works without JavaScript: the form posts and the page shows the done state', function (): void {
    $submission = gdprSubmission();

    $this->post('/api/data/delete', ['link' => deleteLink($submission)])
        ->assertOk()
        ->assertSee('Your data has been removed.');

    expect($submission->fresh()->isDeleted())->toBeTrue();
});

/*
| Commands
*/

it('exports everything held for an email as JSON', function (): void {
    gdprSubmission();
    $lead = ResourceLead::query()->create(['email' => 'ada@example.com', 'first_name' => 'Ada']);
    ResourceDownload::query()->create(['lead_id' => $lead->id, 'resource_slug' => 'a-guide', 'format' => 'pdf']);
    gdprSubmission(['email' => 'someone-else@example.com']);

    $path = storage_path('framework/testing/export-'.uniqid().'.json');
    $this->artisan('gdpr:export', ['email' => 'ADA@example.com', '--output' => $path])->assertSuccessful();

    $export = json_decode((string) file_get_contents($path), true);
    @unlink($path);

    expect($export['audit_submissions'])->toHaveCount(1)
        ->and($export['outbound_emails'])->toHaveCount(1)
        ->and($export['form_submissions'])->toHaveCount(1)
        ->and($export['resource_leads'][0]['downloads'][0]['resource_slug'])->toBe('a-guide');
});

it('erases everything for an email, and only that email', function (): void {
    $mine = gdprSubmission(['pdf_path' => 'audits/mine.pdf']);
    Storage::disk('local')->put('audits/mine.pdf', 'pdf');
    $theirs = gdprSubmission(['email' => 'someone-else@example.com']);
    ResourceLead::query()->create(['email' => 'ada@example.com']);

    $this->artisan('gdpr:erase', ['email' => 'ada@example.com', '--force' => true])->assertSuccessful();

    expect(AuditSubmission::query()->find($mine->id))->toBeNull()
        ->and(AuditSubmission::query()->find($theirs->id))->not->toBeNull()
        ->and(ResourceLead::query()->count())->toBe(0)
        ->and(OutboundEmail::query()->where('to_email', 'ada@example.com')->count())->toBe(0)
        ->and(FormSubmission::query()->count())->toBe(1);

    Storage::disk('local')->assertMissing('audits/mine.pdf');
    expect(GdprDeletion::query()->sole()->email_hash)->toBe(hash('sha256', 'ada@example.com'));
});

it('anonymises instead of deleting when asked, and asks before doing anything', function (): void {
    $submission = gdprSubmission();

    $this->artisan('gdpr:erase', ['email' => 'ada@example.com'])
        ->expectsConfirmation('Erase all of this for ada@example.com?', 'no')
        ->assertFailed();

    expect($submission->fresh()->email)->toBe('ada@example.com');

    $this->artisan('gdpr:erase', ['email' => 'ada@example.com', '--anonymise' => true, '--force' => true])->assertSuccessful();

    expect($submission->fresh()->email)->toBe('redacted@gdpr.local')
        ->and($submission->fresh()->isDeleted())->toBeTrue();
});

it('sweeps by the retention rules, and a dry run deletes nothing', function (): void {
    $staleUnsent = gdprSubmission(['submitted_at' => now()->subDays(91)]);
    $freshUnsent = gdprSubmission(['submitted_at' => now()->subDays(30)]);
    $staleSent = gdprSubmission(['status' => 'sent', 'submitted_at' => now()->subYears(3), 'sent_at' => now()->subMonths(25)]);
    $freshSent = gdprSubmission(['status' => 'sent', 'submitted_at' => now()->subDays(200), 'sent_at' => now()->subMonths(6)]);
    OutboundEmail::query()->create(['to_email' => 'x@example.com', 'subject' => 's', 'template' => 't', 'sent_at' => now()->subMonths(25)]);
    Storage::disk('local')->put('audits/orphan.pdf', 'pdf');

    $this->artisan('gdpr:sweep', ['--dry-run' => true])
        ->expectsOutputToContain('Dry run')
        ->assertSuccessful();

    expect(AuditSubmission::query()->count())->toBe(4);
    Storage::disk('local')->assertExists('audits/orphan.pdf');

    $this->artisan('gdpr:sweep')->assertSuccessful();

    expect(AuditSubmission::query()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$freshUnsent->id, $freshSent->id])->sort()->values()->all())
        ->and(OutboundEmail::query()->where('to_email', 'x@example.com')->count())->toBe(0);

    Storage::disk('local')->assertMissing('audits/orphan.pdf');
    expect($staleUnsent->fresh())->toBeNull()->and($staleSent->fresh())->toBeNull();
});
