{{--
    /studio/audits/{id}: one submission, ported from studio/audits.astro.

    Two forms. The first saves notes, status and markdown. The second renders
    the PDF; a short script copies the markdown being edited into it first,
    so an unsaved edit is what gets rendered, as the live studio did.
--}}
@extends('layouts.base')

@php
    $bare = true;
    $data = $submission->data;
    $status = $submission->isDeleted() ? 'deleted' : $submission->status;
    $sectorAnswers = array_diff_key($data, array_flip(App\Mail\AuditRequestNotification::RESERVED));
    $label = fn (string $key): string => ucfirst(trim(preg_replace('/([A-Z])/', ' $1', $key)));
@endphp

@section('head')
    <x-cms-seo :title="($data['companyName'] ?? $submission->audit_ref).' | Studio'" description="Internal admin for AI readiness audit submissions." :noindex="true" />
@endsection

@section('content')
    @include('studio._styles')

    <section class="py-10 md:py-16">
        <div class="mx-auto w-full max-w-[1080px] px-5 md:px-8">
            <p class="mb-6"><a href="{{ route('studio.index') }}" class="font-body text-[13px] text-text-secondary no-underline">&larr; Back to all submissions</a></p>

            @include('studio._flash')

            @if (session('delete_link'))
                <div class="studio-flash">
                    <label class="mb-2 block font-body text-[11px] tracking-widest text-text-tertiary uppercase" for="delete-link">Self-serve deletion link (never expires)</label>
                    <input id="delete-link" class="studio-input font-mono" readonly value="{{ session('delete_link') }}" onclick="this.select()">
                </div>
            @endif

            <div class="mb-8 grid gap-6 md:grid-cols-[1fr_320px]">
                <div>
                    <p class="mb-2 font-body text-[11px] tracking-widest text-text-tertiary uppercase">{{ $submission->audit_ref }}</p>
                    <h1 class="mb-2 font-display text-[28px] leading-[1.15] text-text-primary md:text-[36px]">{{ $data['companyName'] ?? '—' }}</h1>
                    <p class="text-[14px] text-text-secondary">{{ implode(' · ', array_filter([$data['sector'] ?? null, $data['teamSize'] ?? null])) ?: '—' }}</p>
                </div>
                <div class="border border-border bg-bg-surface p-4" style="border-radius: 3px;">
                    <dl class="grid grid-cols-[100px_1fr] gap-y-2 font-body text-[12px]">
                        <dt class="tracking-widest text-text-tertiary uppercase">Status</dt>
                        <dd><span class="studio-status-badge" data-status="{{ $status }}">{{ str_replace('_', ' ', $status) }}</span></dd>
                        <dt class="tracking-widest text-text-tertiary uppercase">Submitted</dt>
                        <dd class="text-text-primary">{{ $submission->submitted_at?->format('j M Y, H:i') ?? '—' }}</dd>
                        <dt class="tracking-widest text-text-tertiary uppercase">Sent</dt>
                        <dd class="text-text-primary">{{ $submission->sent_at?->format('j M Y, H:i') ?? '—' }}</dd>
                    </dl>
                </div>
            </div>

            @if ($submission->isDeleted())
                <p class="studio-flash is-error">Deleted {{ $submission->deleted_at->format('j M Y') }} ({{ $submission->deletion_reason }}). The personal data has been removed.</p>
            @else
                <section class="mb-8 border border-border bg-bg-surface p-6" style="border-radius: 3px;">
                    <h2 class="mb-4 font-body text-[11px] tracking-widest text-text-tertiary uppercase">Submitter</h2>
                    <dl class="grid gap-y-3 md:grid-cols-[160px_1fr]">
                        <dt class="font-body text-[13px] text-text-tertiary">Name</dt>
                        <dd class="text-[14px] text-text-primary">{{ $data['name'] ?? '—' }}</dd>
                        <dt class="font-body text-[13px] text-text-tertiary">Email</dt>
                        <dd class="text-[14px]"><a href="mailto:{{ $submission->email }}">{{ $submission->email }}</a></dd>
                        <dt class="font-body text-[13px] text-text-tertiary">Website</dt>
                        <dd class="text-[14px]"><a href="{{ $data['website'] ?? '#' }}" target="_blank" rel="noopener noreferrer">{{ $data['website'] ?? '—' }}</a></dd>
                        <dt class="font-body text-[13px] text-text-tertiary">Referrer</dt>
                        <dd class="text-[14px] text-text-primary">{{ $data['referrer'] ?? '—' }}</dd>
                    </dl>
                </section>

                <section class="mb-8">
                    <h2 class="mb-3 font-body text-[11px] tracking-widest text-text-tertiary uppercase">Biggest bottleneck</h2>
                    <p class="border-l-2 border-accent bg-bg-surface p-4 text-[15px] leading-[1.7] text-text-primary">{{ $data['biggestBottleneck'] ?? '—' }}</p>
                </section>

                <section class="mb-8 grid gap-6 md:grid-cols-3">
                    @foreach (['Team size' => $data['teamSize'] ?? '—', 'Budget' => $data['budgetRange'] ?? 'Not stated', 'Six-month win' => $data['sixMonthWin'] ?? '—'] as $heading => $value)
                        <div class="border border-border bg-bg-surface p-4" style="border-radius: 3px;">
                            <p class="mb-1 font-body text-[11px] tracking-widest text-text-tertiary uppercase">{{ $heading }}</p>
                            <p class="text-[15px] text-text-primary">{{ $value }}</p>
                        </div>
                    @endforeach
                </section>

                <section class="mb-8">
                    <h2 class="mb-3 font-body text-[11px] tracking-widest text-text-tertiary uppercase">Sector-specific answers</h2>
                    <div class="border border-border bg-bg-surface p-4" style="border-radius: 3px;">
                        <dl class="grid gap-y-2 text-[14px] md:grid-cols-[200px_1fr]">
                            @forelse ($sectorAnswers as $key => $value)
                                <dt class="font-body text-[12px] tracking-widest text-text-tertiary uppercase">{{ $label($key) }}</dt>
                                <dd class="text-text-primary">{{ is_array($value) ? implode(', ', $value) : $value }}</dd>
                            @empty
                                <dt class="col-span-2 text-[13px] text-text-tertiary italic">No sector-specific answers</dt>
                            @endforelse
                        </dl>
                    </div>
                </section>

                @if (! empty($data['notes']))
                    <section class="mb-8">
                        <h2 class="mb-3 font-body text-[11px] tracking-widest text-text-tertiary uppercase">Anything else they said</h2>
                        <p class="border border-border bg-bg-surface p-4 text-[14px] leading-[1.7] text-text-primary" style="border-radius: 3px;">{{ $data['notes'] }}</p>
                    </section>
                @endif

                <form method="POST" action="{{ route('studio.update', $submission) }}" class="mb-6">
                    @csrf
                    @method('PATCH')

                    <section class="mb-6">
                        <label for="status" class="mb-3 block font-body text-[11px] tracking-widest text-text-tertiary uppercase">Status</label>
                        <select id="status" name="status" class="studio-input max-w-[260px]">
                            @foreach (array_unique([...$statuses, $submission->status]) as $option)
                                <option value="{{ $option }}" @selected($submission->status === $option) @disabled(! in_array($option, $statuses, true))>{{ str_replace('_', ' ', $option) }}</option>
                            @endforeach
                        </select>
                    </section>

                    <section class="mb-6">
                        <label for="admin_notes" class="mb-3 block font-body text-[11px] tracking-widest text-text-tertiary uppercase">My notes</label>
                        <textarea id="admin_notes" name="admin_notes" rows="5" class="studio-input" placeholder="Context before I generate. Quick win to look for, budget gut feel, where I met them, anything that should flavour the audit.">{{ old('admin_notes', $submission->admin_notes) }}</textarea>
                    </section>

                    <section class="mb-4">
                        <label for="audit_markdown" class="mb-3 block font-body text-[11px] tracking-widest text-text-tertiary uppercase">Audit markdown</label>
                        <textarea id="audit_markdown" name="audit_markdown" rows="18" class="studio-input font-mono" placeholder="Paste the audit markdown here. YAML frontmatter at the top (title, subtitle, author, date), then the audit body.">{{ old('audit_markdown', $submission->audit_markdown) }}</textarea>
                    </section>

                    <button type="submit" class="studio-btn-primary">Save</button>
                </form>

                <div class="mb-10 flex flex-wrap items-center gap-3">
                    <form method="POST" action="{{ route('studio.render', $submission) }}" data-render-form>
                        @csrf
                        <input type="hidden" name="markdown" value="">
                        <button type="submit" class="studio-btn-primary">Render PDF</button>
                    </form>

                    @if ($submission->pdf_path)
                        <a href="{{ route('studio.pdf', $submission) }}" target="_blank" rel="noopener" class="studio-btn-secondary">View latest PDF</a>
                    @endif

                    <form method="POST" action="{{ route('studio.delete-link', $submission) }}">
                        @csrf
                        <button type="submit" class="studio-btn-secondary">Mint deletion link</button>
                    </form>
                </div>

                <script>
                    document.querySelector('[data-render-form]')?.addEventListener('submit', (event) => {
                        event.currentTarget.elements.markdown.value = document.getElementById('audit_markdown').value;
                    });
                </script>
            @endif

            <p class="text-[12px] text-text-tertiary">
                IP: {{ $submission->ip_address ?? '—' }} · UA: {{ \Illuminate\Support\Str::limit($submission->user_agent ?? '—', 80) }} · Privacy notice: {{ $submission->privacy_notice_version }}
            </p>
        </div>
    </section>
@endsection
