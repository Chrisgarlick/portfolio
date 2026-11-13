{{--
    The `site-data-deletion` block: the privacy page's "delete your data" form.

    It posts an email address to DataDeletionController::request, which sends
    a signed link to /data/delete for each submission on file (the existing
    two-step deletion, unchanged). resources/js/site/data-delete-request.js
    posts it in the background and says what happens next; without
    JavaScript the form posts and the server answers with a page.

    No CSRF token: public pages have no session. FormGuard's token, timing
    check and honeypot, plus a per-IP limit, do that job, as on every public
    form.
--}}
@php
    $guardFields = app(Cg\Cms\Forms\FormGuard::class)->hiddenFields(App\Http\Controllers\DataDeletionController::FORM);
    $note = $data['note'] ?? 'The link works for 24 hours.';
@endphp
<section class="site-data-deletion" id="delete-your-data" aria-label="Delete your data">
    <div class="kit-container">
        <form class="site-data-deletion-form" method="POST" action="{{ route('data-delete.request', [], false) }}" novalidate data-deletion-request>
            @foreach ($guardFields as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <div class="hp" aria-hidden="true">
                <label for="dd-hp">Website</label>
                <input type="text" id="dd-hp" name="{{ Cg\Cms\Forms\FormGuard::HONEYPOT }}" tabindex="-1" autocomplete="off">
            </div>

            <div class="site-data-deletion-row">
                <div class="kit-field">
                    <label class="kit-field-label" for="dd-email">{{ $data['label'] ?? 'Your email address' }}</label>
                    <input class="kit-input" type="email" id="dd-email" name="email" required autocomplete="email" placeholder="{{ $data['placeholder'] ?? 'you@your-website.co.uk' }}" @if (filled($note)) aria-describedby="dd-note" @endif>
                </div>
                <button type="submit" class="kit-button" data-arrow>{{ $data['button'] ?? 'Send the link' }}</button>
            </div>
            @if (filled($note))
                <p class="site-data-deletion-note" id="dd-note">{{ $note }}</p>
            @endif
            <p class="site-data-deletion-status" role="status" aria-live="polite" data-deletion-status></p>
        </form>
    </div>
</section>
