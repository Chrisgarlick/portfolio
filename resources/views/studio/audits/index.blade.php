{{-- /studio/audits: the submissions list, ported from studio/audits.astro. --}}
@extends('layouts.base')

@php($bare = true)

@section('head')
    <x-cms-seo title="Audit submissions | Studio" description="Internal admin for AI readiness audit submissions." :noindex="true" />
@endsection

@section('content')
    @include('studio._styles')

    <section class="py-10 md:py-16">
        <div class="mx-auto w-full max-w-[1080px] px-5 md:px-8">
            <div class="mb-8 flex items-center justify-between">
                <div>
                    <p class="mb-1 font-body text-[11px] font-medium tracking-[0.18em] text-accent uppercase">Studio</p>
                    <h1 class="font-display text-[28px] leading-[1.1] text-text-primary md:text-[36px]">Audit submissions</h1>
                </div>
                <a href="{{ route('cgcms.admin.dashboard') }}" class="studio-btn-secondary">Admin</a>
            </div>

            @include('studio._flash')

            @if ($submissions->isEmpty())
                <div class="py-12 text-center">
                    <p class="text-[15px] text-text-secondary">No audit submissions yet.</p>
                    <p class="mt-2 text-[13px] text-text-tertiary">Submissions will appear here once visitors complete the form at <a href="/audit" class="text-accent">/audit</a>.</p>
                </div>
            @else
                <div class="overflow-x-auto border border-border bg-bg-surface" style="border-radius: 3px;">
                    <table class="w-full text-left text-[13px]">
                        <thead class="border-b border-border bg-bg-muted">
                            <tr>
                                @foreach (['Ref', 'Company', 'Sector', 'Budget', 'Status', 'Submitted', ''] as $heading)
                                    <th class="px-4 py-3 font-body text-[11px] tracking-widest text-text-tertiary uppercase">{{ $heading }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($submissions as $submission)
                                @php($status = $submission->isDeleted() ? 'deleted' : $submission->status)
                                <tr class="studio-row {{ $submission->isDeleted() ? 'deleted-row' : '' }}">
                                    <td><a href="{{ route('studio.show', $submission) }}" class="font-body text-[12px]">{{ $submission->audit_ref }}</a></td>
                                    <td>
                                        <div class="text-[14px] text-text-primary">{{ $submission->data['companyName'] ?? '—' }}</div>
                                        <div class="text-[12px] text-text-tertiary">{{ $submission->data['name'] ?? '' }}</div>
                                    </td>
                                    <td class="text-text-secondary">{{ $submission->data['sector'] ?? '—' }}</td>
                                    <td class="text-text-secondary">{{ $submission->data['budgetRange'] ?? '—' }}</td>
                                    <td><span class="studio-status-badge" data-status="{{ $status }}">{{ str_replace('_', ' ', $status) }}</span></td>
                                    <td class="text-[12px] text-text-tertiary">{{ $submission->submitted_at?->format('j M Y, H:i') }}</td>
                                    <td>
                                        @if (filled($submission->admin_notes))
                                            <span title="Has notes" style="color: var(--color-accent); font-size: 11px;">●</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>
@endsection
