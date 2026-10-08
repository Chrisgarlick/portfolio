{{--
    /data/delete, ported from data/delete.astro.

    The live page fetched the preview from the API with JavaScript; here the
    server renders whichever state applies, so it works without a script.
    $state is invalid | already | prompt | done. The confirm button is a real
    form; resources/js/site/data-delete.js submits it in the background and
    swaps in the done state, and without JavaScript the form posts normally.
--}}
@extends('layouts.base')

@section('head')
    <x-cms-seo title="Delete your audit data" description="Self-serve removal of your AI readiness audit data. One click." :noindex="true" />
@endsection

@section('content')
    <section class="py-16 md:py-24">
        <div class="mx-auto w-full max-w-[640px] px-5 md:px-8" data-data-delete>
            <p class="mb-3 font-body text-[11px] font-medium tracking-[0.18em] text-accent uppercase">Data deletion</p>
            <h1 class="mb-5 font-display text-[36px] leading-[1.1] text-text-primary md:text-[44px]">Remove your audit data.</h1>

            @if ($state === 'invalid')
                <div class="mb-8">
                    <p class="mb-4 text-[15px] leading-[1.7] text-text-secondary">
                        This link is invalid, expired, or doesn&rsquo;t match a submission.
                    </p>
                    <p class="text-[15px] leading-[1.7] text-text-secondary">
                        If you&rsquo;d like your data removed and the link isn&rsquo;t working, email <a href="mailto:privacy@chrisgarlick.com" class="text-accent hover:text-accent-hover">privacy@chrisgarlick.com</a> with the email address you used and I&rsquo;ll action it within 30 days.
                    </p>
                </div>
            @endif

            @if ($state === 'already')
                <div class="mb-8 border-l-2 border-accent pl-6 md:pl-7">
                    <p class="mb-2 font-body text-[11px] font-medium tracking-[0.18em] text-accent uppercase">Already removed</p>
                    <h2 class="mb-3 font-display text-[24px] text-text-primary">Your data has already been deleted.</h2>
                    <p class="text-[15px] leading-[1.7] text-text-secondary">Nothing else to do. Your submission was removed previously.</p>
                </div>
            @endif

            @if ($state === 'prompt')
                <div data-delete-prompt>
                    <p class="mb-6 text-[15px] leading-[1.7] text-text-secondary md:text-[16px]">
                        You&rsquo;re about to remove the following audit submission from our records:
                    </p>

                    <div class="mb-8 border border-border bg-bg-surface p-5 md:p-6" style="border-radius: 3px;">
                        <dl class="grid grid-cols-[120px_1fr] gap-y-2 font-body text-[13px]">
                            <dt class="tracking-widest text-text-tertiary uppercase">Ref</dt>
                            <dd class="text-text-primary">{{ $submission->audit_ref }}</dd>
                            <dt class="tracking-widest text-text-tertiary uppercase">Email</dt>
                            <dd class="text-text-primary">{{ $submission->email }}</dd>
                            <dt class="tracking-widest text-text-tertiary uppercase">Name</dt>
                            <dd class="text-text-primary">{{ $submission->data['name'] ?? '—' }}</dd>
                            <dt class="tracking-widest text-text-tertiary uppercase">Company</dt>
                            <dd class="text-text-primary">{{ $submission->data['companyName'] ?? '—' }}</dd>
                            <dt class="tracking-widest text-text-tertiary uppercase">Submitted</dt>
                            <dd class="text-text-primary">{{ $submission->submitted_at?->format('j F Y, H:i') }}</dd>
                        </dl>
                    </div>

                    <p class="mb-6 text-[14px] leading-[1.6] text-text-secondary">
                        This deletes the submission, the generated audit PDF (if any), and all associated personal data. It can&rsquo;t be undone.
                    </p>

                    <form method="POST" action="{{ route('data-delete.confirm') }}" class="flex flex-wrap items-center gap-3" data-delete-form>
                        <input type="hidden" name="link" value="{{ $link }}">
                        <button type="submit" class="delete-primary-btn">Yes, delete my data</button>
                        <a href="/" class="font-body text-[13px] tracking-wider text-text-secondary uppercase no-underline hover:text-text-primary">Cancel</a>
                    </form>

                    <p class="mt-4 hidden font-body text-[13px] text-destructive" data-delete-error role="alert"></p>
                </div>
            @endif

            <div class="{{ $state === 'done' ? '' : 'hidden' }} border-l-2 border-accent pl-6 md:pl-7" data-delete-success tabindex="-1">
                <p class="mb-2 font-body text-[11px] font-medium tracking-[0.18em] text-accent uppercase">Done</p>
                <h2 class="mb-3 font-display text-[24px] text-text-primary md:text-[28px]">Your data has been removed.</h2>
                <p class="mb-4 text-[15px] leading-[1.7] text-text-secondary">
                    The submission, any generated audit PDF, and all associated personal data have been deleted. You will not receive any further contact about this enquiry.
                </p>
                <p class="text-[14px] leading-[1.6] text-text-tertiary">
                    If you change your mind in the future, just <a href="/audit" class="text-accent hover:text-accent-hover">start a new audit</a> — there&rsquo;s no record of this one to interfere.
                </p>
            </div>
        </div>
    </section>

    <style>
        .delete-primary-btn { display: inline-flex; align-items: center; justify-content: center; padding: 0.875rem 1.75rem; border: 1px solid var(--color-destructive); border-radius: 3px; background: var(--color-destructive); font-family: var(--font-body); font-size: 13px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em; color: #fff; cursor: pointer; transition: all 0.15s; }
        .delete-primary-btn:hover { opacity: 0.9; }
        .delete-primary-btn:disabled { opacity: 0.5; cursor: not-allowed; }
    </style>
@endsection
