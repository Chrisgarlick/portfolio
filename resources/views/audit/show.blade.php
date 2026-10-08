{{--
    /audit: the AI readiness audit request, ported from audit.astro.

    The steps, sectors and conditional fields come from
    resources/forms/audit-form.yml through App\Support\AuditForm, the same
    definition the controller validates against. Behaviour (steps, conditional
    blocks, validation, submit) is resources/js/site/audit-form.js.

    Without JavaScript every step is visible at once and the form posts to the
    API, which still validates everything the script would have.
--}}
@extends('layouts.base')

@php($steps = $form->steps())

@section('head')
    <x-cms-seo
        title="Free AI Readiness Audit for UK Businesses"
        description="Free personalised AI readiness audit. Tell me about your business in 4 short steps; I'll send back a costed plan covering automation opportunities, indicative pricing and a recommended first build within 24 hours."
        keywords="ai readiness audit, free ai audit uk, business workflow audit, ai implementation audit, custom ai assessment, ai opportunity analysis, ai automation audit, ai readiness check, ai for small business uk"
        :json-ld="[
            [
                '@type' => 'Service',
                'name' => 'AI Readiness Audit',
                'description' => 'Free personalised AI readiness audit for UK businesses. 4-step intake form, costed plan returned within 24 hours.',
                'url' => 'https://chrisgarlick.com/audit',
                'provider' => ['@type' => 'Person', 'name' => 'Chris Garlick', 'url' => 'https://chrisgarlick.com/about'],
                'areaServed' => ['@type' => 'Country', 'name' => 'United Kingdom'],
                'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'GBP'],
            ],
            [
                '@type' => 'HowTo',
                'name' => 'How the audit works',
                'step' => [
                    ['@type' => 'HowToStep', 'position' => 1, 'name' => 'Tell me about your business', 'text' => 'Name, email, company name and website.'],
                    ['@type' => 'HowToStep', 'position' => 2, 'name' => 'Pick your sector', 'text' => 'Law firm, accountancy, agency, consultancy, recruitment, architecture, or other professional services.'],
                    ['@type' => 'HowToStep', 'position' => 3, 'name' => 'Answer a few sector-specific questions', 'text' => 'How your team operates, what software you use, where the manual time goes.'],
                    ['@type' => 'HowToStep', 'position' => 4, 'name' => 'Receive your personalised audit within 24 hours', 'text' => 'Workflows worth automating, indicative pricing, and a recommended first engagement.'],
                ],
            ],
        ]"
    />
@endsection

@section('content')
    <section class="py-16 md:py-24">
        <div class="mx-auto w-full max-w-[680px] px-5 md:px-8">

            <div id="audit-intro">
                <p class="mb-3 font-body text-[11px] font-medium tracking-[0.18em] text-accent uppercase">AI Readiness Audit</p>
                <h1 class="mb-5 font-display text-[36px] leading-[1.1] text-text-primary md:text-[48px]">
                    Tell me about your business. Get a costed AI plan back.
                </h1>
                <p class="mb-10 text-[15px] leading-[1.7] text-text-secondary md:text-[16px]">
                    Four short steps. Three minutes. Within 24 hours I&rsquo;ll send back a personalised audit: the workflows in your business worth automating, indicative pricing, and a recommended first engagement. No pitch deck.
                </p>
            </div>

            <div id="audit-progress" class="mb-8 hidden" data-audit-progress>
                <div class="mb-3 flex items-center justify-between font-body text-[12px] tracking-[0.14em] text-text-tertiary uppercase">
                    <span>Step <span id="step-current">1</span> of {{ count($steps) }}</span>
                    <span id="step-title-display"></span>
                </div>
                <div class="h-[2px] w-full bg-bg-muted">
                    <div id="progress-bar" class="h-full bg-accent transition-all duration-300" style="width: {{ round(100 / max(1, count($steps))) }}%;"></div>
                </div>
            </div>

            <form id="audit-form" method="POST" action="{{ route('audit-intake.store') }}" class="space-y-10" novalidate data-audit-form>
                @foreach ($hidden as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach

                {{-- Visually hidden, not display:none. Some bots skip the latter. --}}
                <div class="hp" aria-hidden="true">
                    <label for="{{ Cg\Cms\Forms\FormGuard::HONEYPOT }}">Website</label>
                    <input type="text" tabindex="-1" autocomplete="off" id="{{ Cg\Cms\Forms\FormGuard::HONEYPOT }}" name="{{ Cg\Cms\Forms\FormGuard::HONEYPOT }}">
                </div>

                @foreach ($steps as $index => $step)
                    <fieldset class="audit-step" data-step="{{ $index }}" data-step-id="{{ $step['id'] }}" aria-labelledby="step-title-{{ $step['id'] }}">
                        <legend id="step-title-{{ $step['id'] }}" class="sr-only">{{ $step['title'] }}</legend>
                        <h2 class="mb-6 font-display text-[22px] text-text-primary md:text-[26px]">{{ $step['title'] }}</h2>

                        @foreach ($step['fields'] ?? [] as $field)
                            @include('audit._field', ['field' => $field])
                        @endforeach

                        @foreach ($step['fieldsBySector'] ?? [] as $sectorKey => $sectorFields)
                            <div class="audit-sector-block" data-sector-block="{{ $sectorKey }}">
                                @foreach ($sectorFields as $field)
                                    @include('audit._field', ['field' => $field, 'sector' => $sectorKey])
                                @endforeach
                            </div>
                        @endforeach
                    </fieldset>
                @endforeach

                <div class="flex items-center justify-between gap-4 pt-4">
                    <button type="button" id="back-btn" class="audit-back-btn hidden">&larr; Back</button>
                    <div class="ml-auto flex gap-3">
                        <button type="button" id="next-btn" class="audit-primary-btn hidden">Next &rarr;</button>
                        <button type="submit" id="submit-btn" class="audit-primary-btn">Submit audit request &rarr;</button>
                    </div>
                </div>

                <p id="form-error" class="audit-form-error hidden" role="alert"></p>
            </form>

            <div id="success-panel" class="mt-12 hidden border-l-2 border-accent pl-6 md:pl-7" tabindex="-1">
                <p class="mb-2 font-body text-[11px] font-medium tracking-[0.18em] text-accent uppercase">Submitted</p>
                <h2 class="mb-4 font-display text-[28px] leading-[1.15] text-text-primary md:text-[36px]">Your audit is being prepared.</h2>
                <p class="mb-4 text-[15px] leading-[1.7] text-text-secondary md:text-[16px]">
                    I'll review your details and send back a personalised AI readiness report within 24 hours. It covers the workflows worth automating, indicative pricing, and a recommended first engagement.
                </p>
                <p class="mb-6 text-[15px] leading-[1.7] text-text-secondary md:text-[16px]">
                    Check your inbox for a confirmation email. If anything else comes to mind in the meantime, reply to it and I'll fold it into the audit.
                </p>
                <p class="font-body text-[12px] tracking-widest text-text-tertiary uppercase">Reference: <span id="audit-ref"></span></p>
            </div>
        </div>
    </section>

    {{-- Scoped in audit.astro; inline here so the page owns its own styles. --}}
    <style>
        .audit-label { display: block; margin-bottom: 0.5rem; font-family: var(--font-body); font-size: 14px; color: var(--color-text-primary); line-height: 1.5; }
        .audit-req { color: var(--color-accent); margin-left: 0.2rem; }
        .audit-help { display: block; margin-top: 0.25rem; font-size: 12px; color: var(--color-text-tertiary); font-style: italic; }
        .audit-input, .audit-textarea { display: block; width: 100%; box-sizing: border-box; padding: 0.75rem 1rem; border: 1px solid var(--color-border); border-radius: 3px; background-color: var(--color-bg-surface); font-family: var(--font-body); font-size: 15px; color: var(--color-text-primary); transition: border-color 0.15s; outline: none; }
        .audit-textarea { resize: vertical; min-height: 5rem; }
        .audit-input:focus, .audit-textarea:focus { border-color: var(--color-accent); }
        .audit-input::placeholder, .audit-textarea::placeholder { color: var(--color-text-tertiary); }
        .audit-options { display: grid; grid-template-columns: 1fr; gap: 0.5rem; }
        @media (min-width: 640px) { .audit-options { grid-template-columns: 1fr 1fr; } }
        .audit-option { display: flex; align-items: center; gap: 0.625rem; padding: 0.75rem 1rem; border: 1px solid var(--color-border); border-radius: 3px; background: var(--color-bg-surface); cursor: pointer; font-family: var(--font-body); font-size: 14px; color: var(--color-text-primary); transition: border-color 0.15s, background-color 0.15s; }
        .audit-option:hover { border-color: var(--color-border-hover); }
        .audit-option:has(input:checked) { border-color: var(--color-accent); background-color: var(--color-accent-light); }
        .audit-option input { accent-color: var(--color-accent); }
        .audit-error { margin-top: 0.4rem; font-size: 12px; color: var(--color-destructive); font-family: var(--font-body); }
        .audit-form-error { margin-top: 1rem; padding: 0.75rem 1rem; border: 1px solid var(--color-destructive); background: var(--color-error-bg); border-radius: 3px; font-size: 13px; color: var(--color-destructive); }
        .audit-primary-btn { display: inline-flex; align-items: center; justify-content: center; padding: 0.875rem 1.75rem; border: 1px solid var(--color-accent); border-radius: 3px; background: var(--color-accent); font-family: var(--font-body); font-size: 13px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em; color: #fff; cursor: pointer; transition: all 0.15s; }
        .audit-primary-btn:hover { background: var(--color-accent-hover); border-color: var(--color-accent-hover); }
        .audit-primary-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .audit-back-btn { padding: 0.75rem 1.5rem; border: 1px solid var(--color-border); border-radius: 3px; background: transparent; font-family: var(--font-body); font-size: 13px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-text-secondary); cursor: pointer; transition: border-color 0.15s, color 0.15s; }
        .audit-back-btn:hover { border-color: var(--color-border-hover); color: var(--color-text-primary); }
    </style>
@endsection
