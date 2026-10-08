<?php

declare(strict_types=1);

use App\Models\AuditSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
|------------------------------------------------------------------------------
| Studio: reviewing audit requests (/studio/audits)
|------------------------------------------------------------------------------
*/

beforeEach(function (): void {
    Storage::fake('local');
});

function studioSubmission(array $overrides = []): AuditSubmission
{
    return AuditSubmission::query()->create(array_replace([
        'audit_ref' => 'CG-'.now()->year.'-001',
        'email' => 'ada@example.com',
        'data' => [
            'name' => 'Ada Lovelace',
            'companyName' => 'Analytical Engines Ltd',
            'website' => 'https://example.com',
            'sector' => 'law-firm',
            'teamSize' => '6-15',
            'biggestBottleneck' => 'Retyping client intake forms.',
            'monthlyMatters' => '10-30',
        ],
        'status' => 'submitted',
        'privacy_notice_version' => '2026-05-14',
        'submitted_at' => now(),
    ], $overrides));
}

it('sends anyone not signed in to the admin sign-in', function (): void {
    $submission = studioSubmission();

    $this->get('/studio/audits')->assertRedirect('/admin/signin');
    $this->get("/studio/audits/{$submission->id}")->assertRedirect('/admin/signin');
    $this->get("/studio/audits/{$submission->id}/pdf")->assertRedirect('/admin/signin');
});

it('lists submissions and shows one in full, noindexed', function (): void {
    $submission = studioSubmission();
    $this->actingAs(User::factory()->create());

    $this->get('/studio/audits')->assertOk()
        ->assertSee($submission->audit_ref)
        ->assertSee('Analytical Engines Ltd');

    $this->get("/studio/audits/{$submission->id}")->assertOk()
        ->assertSee('Retyping client intake forms.')
        ->assertSee('Monthly Matters')
        ->assertSee('noindex', false);
});

it('saves notes, markdown and status, and stamps sent_at the first time it is sent', function (): void {
    $submission = studioSubmission();
    $this->actingAs(User::factory()->create());

    $this->patch("/studio/audits/{$submission->id}", [
        'admin_notes' => 'Met on LinkedIn.',
        'audit_markdown' => "# Audit\n\nBody.",
        'status' => 'sent',
    ])->assertRedirect()->assertSessionHas('success');

    $submission->refresh();

    expect($submission->admin_notes)->toBe('Met on LinkedIn.')
        ->and($submission->audit_markdown)->toBe("# Audit\n\nBody.")
        ->and($submission->status)->toBe('sent')
        ->and($submission->sent_at)->not->toBeNull();

    $this->patch("/studio/audits/{$submission->id}", ['status' => 'teleported'])->assertSessionHasErrors('status');
});

it('renders the markdown to a PDF through Typeset and serves it inline, never cached', function (): void {
    config(['services.typeset.key' => 'test-key']);
    Http::fake(['*/api/render' => Http::response('%PDF-1.7 fake', 200)]);

    $submission = studioSubmission();
    $this->actingAs(User::factory()->create());

    $this->post("/studio/audits/{$submission->id}/render", ['markdown' => "# Unsaved edit\n\nBody."])
        ->assertRedirect()
        ->assertSessionHas('success');

    $submission->refresh();
    expect($submission->pdf_path)->toBe("audits/{$submission->audit_ref}.pdf")
        ->and($submission->audit_markdown)->toBe("# Unsaved edit\n\nBody.");

    Http::assertSent(fn ($request) => $request['format'] === 'pdf'
        && $request['client'] === config('services.typeset.audit_client')
        && str_contains($request['content'], 'Unsaved edit'));

    $pdf = $this->get("/studio/audits/{$submission->id}/pdf")->assertOk();

    expect($pdf->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($pdf->headers->get('Cache-Control'))->toContain('no-store')
        ->and($pdf->getContent())->toBe('%PDF-1.7 fake');
});

it('refuses to render nothing, and reports a Typeset failure rather than throwing', function (): void {
    $submission = studioSubmission();
    $this->actingAs(User::factory()->create());

    $this->post("/studio/audits/{$submission->id}/render")->assertSessionHas('error', 'Add some markdown before rendering.');

    config(['services.typeset.key' => null]);
    $this->post("/studio/audits/{$submission->id}/render", ['markdown' => '# Audit'])
        ->assertSessionHas('error', 'PDF/DOCX rendering is not configured.');
});

it('mints a signed deletion link that opens the deletion page', function (): void {
    $submission = studioSubmission();
    $this->actingAs(User::factory()->create());

    $link = $this->post("/studio/audits/{$submission->id}/delete-link")->assertRedirect()->getSession()->get('delete_link');

    expect($link)->toContain('/data/delete?')->toContain('signature=');

    $this->get($link)->assertOk()->assertSee($submission->audit_ref);
});
