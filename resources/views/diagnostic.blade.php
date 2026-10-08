{{--
    The fit check, ported from diagnostic.astro.

    Behaviour in resources/js/site/diagnostic.js. The score is worked out on
    the server (App\Support\FitScore) and returned; the result panel's copy
    lives in the script, as it did on the live page.

    `bare` comes from the controller: no navigation, a one-line footer.
--}}
@extends('layouts.base')

@section('head')
    <x-cms-seo
        title="Quick fit check"
        description="Five-minute self-assessment to figure out whether AI implementation makes sense for your business, and which lane to start in."
        :noindex="true"
    />
@endsection

@section('content')
    <section class="py-16 md:py-24">
        <div class="mx-auto w-full max-w-[640px] px-5 md:px-8">

            <div id="diagnostic-intro">
                <p class="mb-3 font-body text-[11px] font-medium tracking-[0.18em] text-accent uppercase">Quick fit check</p>
                <h1 class="mb-5 font-display text-[36px] leading-[1.1] text-text-primary md:text-[48px]">
                    Is AI implementation worth doing for you right now?
                </h1>
                <p class="mb-12 text-[15px] leading-[1.7] text-text-secondary md:text-[16px]">
                    Five questions. Three minutes. At the end I'll tell you honestly whether a build makes sense for where you are, and if it does, which lane to start in.
                </p>
            </div>

            <form id="diagnostic-form" class="space-y-10" novalidate data-diagnostic data-endpoint="{{ route('diagnostic.store', [], false) }}">
                @foreach ($hidden as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach

                {{-- Visually hidden, not display:none. Some bots skip the latter. --}}
                <div class="hp" aria-hidden="true">
                    <label for="{{ Cg\Cms\Forms\FormGuard::HONEYPOT }}">Website</label>
                    <input type="text" tabindex="-1" autocomplete="off"
                           id="{{ Cg\Cms\Forms\FormGuard::HONEYPOT }}"
                           name="{{ Cg\Cms\Forms\FormGuard::HONEYPOT }}">
                </div>

                <fieldset>
                    <legend class="mb-3 font-display text-[18px] text-text-primary md:text-[20px]">01. What type of business are you?</legend>
                    <div class="grid gap-2 md:grid-cols-2">
                        @foreach ($businessTypes as $option)
                            <label class="diag-option">
                                <input type="radio" name="businessType" value="{{ $option }}" required>
                                <span>{{ $option }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="mb-3 font-display text-[18px] text-text-primary md:text-[20px]">02. What's the task you most want to automate?</legend>
                    <p class="mb-2 font-body text-[12px] text-text-tertiary">One or two lines is fine. Specific is better than abstract.</p>
                    <textarea name="task" required rows="3" maxlength="500"
                              placeholder="e.g. assembling our monthly client reports from three different tools"
                              class="diag-textarea"></textarea>
                </fieldset>

                <fieldset>
                    <legend class="mb-3 font-display text-[18px] text-text-primary md:text-[20px]">03. How many hours per week does it currently take?</legend>
                    <div class="grid gap-2 md:grid-cols-3">
                        <label class="diag-option"><input type="radio" name="hours" value="&lt;2h" required><span>Less than 2h</span></label>
                        <label class="diag-option"><input type="radio" name="hours" value="2-10h" required><span>2&ndash;10h</span></label>
                        <label class="diag-option"><input type="radio" name="hours" value="10h+" required><span>10h or more</span></label>
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="mb-3 font-display text-[18px] text-text-primary md:text-[20px]">04. What tools or systems are involved?</legend>
                    <p class="mb-2 font-body text-[12px] text-text-tertiary">Optional. Just the names. CRM, accounting tool, inbox, anything else.</p>
                    <input type="text" name="stack" maxlength="300" placeholder="e.g. Notion, Xero, Gmail" class="diag-input">
                </fieldset>

                <fieldset>
                    <legend class="mb-3 font-display text-[18px] text-text-primary md:text-[20px]">05. What's your priority right now?</legend>
                    <div class="grid gap-2 md:grid-cols-2">
                        @foreach ($priorities as $option)
                            <label class="diag-option">
                                <input type="radio" name="priority" value="{{ $option }}" required>
                                <span>{{ $option }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="mb-3 font-display text-[18px] text-text-primary md:text-[20px]">
                        Where should I follow up? <span class="font-body text-[12px] font-normal text-text-tertiary">(optional)</span>
                    </legend>
                    <input type="email" name="email" autocomplete="email" placeholder="you@firm.co.uk" class="diag-input">
                    <p class="mt-2 font-body text-[12px] text-text-tertiary">Skip if you'd rather see the result and reach out yourself.</p>
                </fieldset>

                <div>
                    <button type="submit" class="diag-submit">
                        <span data-state="idle">See my result &rarr;</span>
                        <span data-state="loading" class="hidden">Working it out&hellip;</span>
                    </button>
                    <p id="diag-error" class="mt-3 hidden font-body text-[13px] text-red-600" role="alert"></p>
                </div>
            </form>

            <div id="diagnostic-result" class="mt-10 hidden border-l-2 border-accent pl-6 md:pl-7" tabindex="-1">
                <p class="mb-2 font-body text-[11px] font-medium tracking-[0.18em] text-accent uppercase" data-result-tier>Result</p>
                <h2 class="mb-4 font-display text-[28px] leading-[1.15] text-text-primary md:text-[36px]" data-result-heading></h2>
                <p class="mb-6 text-[15px] leading-[1.7] text-text-secondary md:text-[16px]" data-result-body></p>
                <a href="#"
                   class="inline-flex items-center gap-3 border border-accent bg-accent px-6 py-3 font-body text-[13px] font-medium tracking-[0.12em] text-white uppercase no-underline transition-all duration-150 hover:border-accent-hover hover:bg-accent-hover"
                   style="border-radius: 3px;"
                   data-result-cta>
                    <span data-result-cta-label>Continue</span>
                    <span aria-hidden="true">&rarr;</span>
                </a>
            </div>

        </div>
    </section>

    {{-- Scoped to the page in the Astro original; only this page uses them. --}}
    <style>

  .diag-option {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    padding: 0.75rem 1rem;
    border: 1px solid var(--color-border);
    border-radius: 3px;
    background: var(--color-bg-surface);
    cursor: pointer;
    font-family: var(--font-body);
    font-size: 14px;
    color: var(--color-text-primary);
    transition: border-color 0.15s, background-color 0.15s;
  }
  .diag-option:hover { border-color: var(--color-border-hover); }
  .diag-option:has(input:checked) {
    border-color: var(--color-accent);
    background-color: var(--color-accent-light);
  }
  .diag-option input { accent-color: var(--color-accent); }

  .diag-input,
  .diag-textarea {
    display: block;
    width: 100%;
    box-sizing: border-box;
    padding: 0.75rem 1rem;
    border: 1px solid var(--color-border);
    border-radius: 3px;
    background-color: var(--color-bg-surface);
    font-family: var(--font-body);
    font-size: 15px;
    color: var(--color-text-primary);
    transition: border-color 0.2s;
    outline: none;
  }
  .diag-textarea { resize: vertical; min-height: 5rem; }
  .diag-input:focus,
  .diag-textarea:focus { border-color: var(--color-accent); }

  .diag-submit {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.875rem 2rem;
    border: 1px solid var(--color-accent);
    border-radius: 3px;
    background-color: var(--color-accent);
    font-family: var(--font-body);
    font-size: 13px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #fff;
    cursor: pointer;
    transition: all 0.2s;
  }
  .diag-submit:hover {
    background-color: var(--color-accent-hover);
    border-color: var(--color-accent-hover);
  }
  .diag-submit:disabled { opacity: 0.6; cursor: not-allowed; }
    </style>
@endsection
