<?php

declare(strict_types=1);

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\AuditSubmission;
use App\Services\TypesetClient;
use App\Services\TypesetException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Reviewing audit requests and producing the deliverable, ported from
 * studio/audits.astro and the /api/admin/audits endpoints.
 *
 * Server-rendered pages behind the CMS admin's session, where the live site
 * was a client-side app behind a shared secret kept in localStorage. One
 * login, real CSRF, and nothing to leak from a browser.
 */
final class AuditReviewController extends Controller
{
    /** The workflow the live studio used, in order. */
    public const STATUSES = ['submitted', 'ready_to_send', 'sent'];

    private const PDF_DIR = 'audits';

    public function index(): View
    {
        return view('studio.audits.index', [
            'submissions' => AuditSubmission::query()
                ->orderByDesc('submitted_at')
                ->limit(100)
                ->get(),
        ]);
    }

    public function show(AuditSubmission $submission): View
    {
        return view('studio.audits.show', [
            'submission' => $submission,
            'statuses' => self::STATUSES,
        ]);
    }

    /**
     * Notes, status and the audit markdown.
     *
     * Moving to `sent` stamps sent_at the first time, which the retention
     * sweep counts from: 24 months after sending, not after submitting.
     */
    public function update(Request $request, AuditSubmission $submission): RedirectResponse
    {
        $validated = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:20000'],
            'audit_markdown' => ['nullable', 'string', 'max:500000'],
            'status' => ['sometimes', Rule::in(self::STATUSES)],
        ]);

        if ($submission->isDeleted()) {
            return back()->with('error', 'This submission was deleted at the prospect\'s request.');
        }

        // Only what was sent, so a request carrying just a status cannot
        // empty the notes or the markdown.
        foreach (['admin_notes', 'audit_markdown'] as $key) {
            if (array_key_exists($key, $validated)) {
                $submission->{$key} = $validated[$key];
            }
        }

        if (isset($validated['status'])) {
            $submission->status = $validated['status'];

            if ($validated['status'] === 'sent' && $submission->sent_at === null) {
                $submission->sent_at = now();
            }
        }

        $submission->save();

        return back()->with('success', 'Saved.');
    }

    /**
     * Render the audit markdown to PDF through Typeset and keep it.
     *
     * Takes the markdown in the request when given, as the live endpoint did,
     * so an edit that has not been saved yet is what gets rendered; and saves
     * it, so the stored markdown is always the one that produced the PDF.
     */
    public function render(Request $request, AuditSubmission $submission, TypesetClient $typeset): RedirectResponse
    {
        $markdown = (string) ($request->input('markdown') ?: $submission->audit_markdown);

        if (trim($markdown) === '') {
            return back()->with('error', 'Add some markdown before rendering.');
        }

        $submission->forceFill(['audit_markdown' => $markdown])->save();

        try {
            $pdf = $typeset->render(
                name: 'audit-'.$submission->audit_ref,
                content: $markdown,
                format: 'pdf',
                client: (string) config('services.typeset.audit_client'),
            );
        } catch (TypesetException $e) {
            return back()->with('error', $e->getMessage());
        }

        $path = self::PDF_DIR.'/'.$submission->audit_ref.'.pdf';
        Storage::disk('local')->put($path, $pdf);
        $submission->forceFill(['pdf_path' => $path])->save();

        return back()->with('success', 'PDF rendered.');
    }

    public function pdf(AuditSubmission $submission): Response
    {
        $path = $submission->pdf_path;
        $disk = Storage::disk('local');

        if ($path === null || ! $disk->exists($path)) {
            throw new NotFoundHttpException('No PDF has been rendered yet.');
        }

        return response((string) $disk->get($path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$submission->audit_ref.'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * The prospect's self-serve deletion link.
     *
     * Signed with no expiry: it goes in the audit email, and a prospect must
     * be able to use it whenever they choose. Replaces the live mint endpoint
     * that needed a bearer secret and curl.
     */
    public function deleteLink(AuditSubmission $submission): RedirectResponse
    {
        return back()
            ->with('success', 'Deletion link created.')
            ->with('delete_link', URL::signedRoute('data-delete', ['submission' => $submission->id]));
    }
}
