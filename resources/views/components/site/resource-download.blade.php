{{--
    The email gate for a free download, and the format picker that replaces it
    once this device has given an email. Used on an article that carries a
    download, and on the old /resources pages.

    The page is served from the page cache, identical for everyone, so the
    swap happens in resources/js/site/resource-gate.js from the cg_lead_flag
    cookie, and the picker's plain links are authorised by the HttpOnly
    cg_lead cookie. One per page: the script finds it by id.

    No CSRF token: the page has no session. FormGuard's rotating token, the
    timing check and the honeypot do that job, plus a per-IP limit.
--}}
@props(['resource'])

@php
    $hasDocx = in_array($resource->value('has_docx'), [true, 1, '1', 'yes'], true);
    $formats = $hasDocx ? 'PDF, DOCX or markdown' : 'PDF or markdown';
    $summary = (string) $resource->value('summary', '');
    $guardFields = app(Cg\Cms\Forms\FormGuard::class)->hiddenFields('resource-gate');
    $field = 'w-full rounded-md border border-border bg-bg-primary px-3.5 py-2.5 text-[15px] text-text-primary placeholder:text-text-tertiary focus:border-accent focus:outline-none';
    $label = 'mb-1.5 block text-[13px] font-medium text-text-secondary';
@endphp

<aside {{ $attributes->merge(['class' => 'rounded-[10px] border border-border bg-bg-surface p-7 md:p-9']) }} aria-label="Free download">
    <div id="gate-container">
        <div id="resource-gate" data-slug="{{ $resource->slug }}" class="grid gap-8 md:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)]">
            <div>
                <span class="mb-4 block h-1 w-10 rounded-full bg-accent" aria-hidden="true"></span>
                <p class="text-[13px] font-semibold tracking-[0.08em] text-accent uppercase">Free download</p>
                <h2 class="mt-3 font-display text-[32px] leading-[1.05] md:text-[38px]">{{ $resource->title }}</h2>
                @if ($summary !== '')
                    <p class="mt-4 text-[16px] leading-[1.6] text-text-secondary">{{ $summary }}</p>
                @endif
                <p class="mt-4 text-[14px] leading-[1.6] text-text-tertiary">Enter your email and I will send you a copy. Pick {{ $formats }} after you submit.</p>
            </div>

            <form id="resource-form" class="space-y-4" novalidate action="/api/resources/request" method="POST">
                <input type="hidden" name="slug" value="{{ $resource->slug }}">
                @foreach ($guardFields as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach
                <div class="hp" aria-hidden="true">
                    <label for="rg-hp">Website</label>
                    <input type="text" id="rg-hp" name="{{ Cg\Cms\Forms\FormGuard::HONEYPOT }}" tabindex="-1" autocomplete="off">
                </div>

                <div>
                    <label for="rg-email" class="{{ $label }}">Email</label>
                    <input type="email" id="rg-email" name="email" required autocomplete="email" placeholder="you@company.co.uk" class="{{ $field }}">
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="rg-firstName" class="{{ $label }}">First name <span class="text-text-tertiary">(optional)</span></label>
                        <input type="text" id="rg-firstName" name="firstName" autocomplete="given-name" class="{{ $field }}">
                    </div>
                    <div>
                        <label for="rg-company" class="{{ $label }}">Company <span class="text-text-tertiary">(optional)</span></label>
                        <input type="text" id="rg-company" name="company" autocomplete="organization" class="{{ $field }}">
                    </div>
                </div>

                <div>
                    <label for="rg-sector" class="{{ $label }}">Sector <span class="text-text-tertiary">(optional)</span></label>
                    <select id="rg-sector" name="sector" class="{{ $field }}">
                        <option value="">Select</option>
                        <option value="Legal">Legal / Law firm</option>
                        <option value="Accountancy">Accountancy</option>
                        <option value="Agency">Agency</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <label class="flex cursor-pointer items-start gap-2.5">
                    <input type="checkbox" name="marketingConsent" class="mt-1 h-4 w-4 cursor-pointer accent-accent">
                    <span class="text-[14px] leading-[1.55] text-text-secondary">Send me occasional emails with new resources and notes. No spam, unsubscribe any time.</span>
                </label>

                <button type="submit" class="inline-block rounded-full bg-text-primary px-6 py-3 text-[15px] font-semibold text-bg-primary transition-opacity hover:opacity-85 disabled:cursor-not-allowed disabled:opacity-60">
                    <span data-state="idle">Send me the download &rarr;</span>
                    <span data-state="loading" class="hidden">Sending&hellip;</span>
                </button>

                <p id="rg-error" class="hidden text-[14px] text-red-700" role="alert"></p>

                <p class="text-[12px] leading-[1.6] text-text-tertiary">By submitting, you agree to receive the download by email. See the <a href="/privacy" class="text-text-tertiary underline hover:text-text-secondary">privacy policy</a>.</p>
            </form>
        </div>
    </div>

    <div id="picker-container" class="hidden">
        <x-site.format-picker :slug="$resource->slug" />
    </div>
</aside>
