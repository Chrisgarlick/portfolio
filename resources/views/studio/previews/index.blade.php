{{-- /studio/previews: each client preview's link and password, and whether they've looked. --}}
@extends('layouts.base')

@php($bare = true)

@section('head')
    <x-cms-seo title="Client previews | Studio" description="Internal admin for client design previews." :noindex="true" />
@endsection

@section('content')
    @include('studio._styles')
    <style>
        .preview-secret { font-family: var(--font-mono, ui-monospace, monospace); font-size: 12px; padding: 0.5rem 0.75rem; }
        .preview-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .preview-actions form { margin: 0; }
        .preview-actions button, .preview-actions a { padding: 0.4rem 0.75rem; font-size: 11px; }
        .preview-actions button:disabled { opacity: 0.5; cursor: wait; }
        .studio-status-badge[data-status="on"] { background: #E8F4EA; color: #2d7d46; }
        .studio-status-badge[data-status="expired"] { background: #FBE9E7; color: #6B6560; }
        .preview-row td { vertical-align: top; }
        .preview-row details summary { cursor: pointer; color: var(--color-accent); font-size: 12px; }
    </style>

    <section class="py-10 md:py-16">
        <div class="mx-auto w-full max-w-[1080px] px-5 md:px-8">
            <div class="mb-8 flex items-center justify-between">
                <div>
                    <p class="mb-1 font-body text-[11px] font-medium tracking-[0.18em] text-accent uppercase">Studio</p>
                    <h1 class="font-display text-[28px] leading-[1.1] text-text-primary md:text-[36px]">Client previews</h1>
                </div>
                <div class="preview-actions">
                    <a href="{{ route('cgcms.admin.entries.create', 'preview') }}" class="studio-btn-primary">New preview</a>
                    <a href="{{ route('cgcms.admin.dashboard') }}" class="studio-btn-secondary">Admin</a>
                </div>
            </div>

            @include('studio._flash')

            @if ($previews->isEmpty())
                <div class="py-12 text-center">
                    <p class="text-[15px] text-text-secondary">No client previews yet.</p>
                    <p class="mt-2 text-[13px] text-text-tertiary">Add one in the admin under Client previews, then publish it to switch it on.</p>
                </div>
            @else
                <div class="overflow-x-auto border border-border bg-bg-surface" style="border-radius: 3px;">
                    <table class="w-full text-left text-[13px]">
                        <thead class="border-b border-border bg-bg-muted">
                            <tr>
                                @foreach (['Client', 'Status', 'Link and password', 'Design', 'Views', ''] as $heading)
                                    <th class="px-4 py-3 font-body text-[11px] tracking-widest text-text-tertiary uppercase">{{ $heading }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($previews as $preview)
                                @php($entry = $preview['entry'])
                                @php($access = $preview['access'])
                                @php($expired = $preview['expires_at']?->isPast() ?? false)
                                @php($status = $entry->status !== 'published' ? 'off' : ($expired ? 'expired' : 'on'))
                                <tr class="studio-row preview-row">
                                    <td>
                                        <a href="{{ route('cgcms.admin.entries.edit', ['preview', $entry->id]) }}" class="text-[14px]">{{ $entry->title }}</a>
                                        <div class="text-[12px] text-text-tertiary">/{{ $entry->slug }}</div>
                                    </td>
                                    <td>
                                        <span class="studio-status-badge" data-status="{{ $status }}">{{ $status }}</span>
                                        @if ($preview['expires_at'])
                                            <div class="mt-1 text-[12px] text-text-tertiary">{{ $expired ? 'Ended' : 'Until' }} {{ $preview['expires_at']->format('j M Y') }}</div>
                                        @endif
                                    </td>
                                    <td style="min-width: 320px;">
                                        <label class="sr-only" for="link-{{ $entry->id }}">Link for {{ $entry->title }}</label>
                                        <input id="link-{{ $entry->id }}" class="studio-input preview-secret" type="text" readonly value="{{ $access->link() }}">
                                        <details class="mt-2">
                                            <summary>Show password</summary>
                                            <p class="preview-secret mt-1">{{ $access->password }}</p>
                                        </details>
                                    </td>
                                    <td style="min-width: 190px;">
                                        @php($file = $entry->value('design_file'))
                                        @if ($access->publish_status === 'published')
                                            <div class="text-[13px] text-text-primary">Published {{ $access->published_at?->timezone('Europe/London')->format('j M, H:i') }}</div>
                                        @elseif (in_array($access->publish_status, ['queued', 'publishing'], true))
                                            <div class="text-[13px] text-text-primary">Publishing…{{ $access->publish_progress ? ' '.$access->publish_progress : '' }}</div>
                                            <div class="text-[12px] text-text-tertiary">Design is rendering it: reload to check, or watch it in Design's Exports panel</div>
                                        @elseif ($access->publish_status === 'failed')
                                            <div class="text-[13px]" style="color: var(--color-destructive);">Failed: {{ $access->publish_error }}</div>
                                        @else
                                            <div class="text-[12px] text-text-tertiary">{{ filled($file) ? 'Not published from here yet' : 'No Design file set' }}</div>
                                        @endif
                                        @if (filled($file))
                                            <div class="preview-actions mt-2">
                                                <form method="post" action="{{ route('studio.previews.publish', $entry->id) }}">
                                                    @csrf
                                                    <button type="submit" class="studio-btn-primary" @disabled(in_array($access->publish_status, ['queued', 'publishing'], true))>Publish from Design</button>
                                                </form>
                                                <a class="studio-btn-secondary" href="{{ rtrim(config('site.previews.design_url'), '/') }}/files/{{ $file }}" target="_blank" rel="noopener">Open in Design</a>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-text-secondary">
                                        {{ $preview['views'] }}
                                        <div class="text-[12px] text-text-tertiary">{{ $access->last_viewed_at ? 'Last '.$access->last_viewed_at->diffForHumans() : 'Not opened yet' }}</div>
                                    </td>
                                    <td>
                                        <div class="preview-actions">
                                            <form method="post" action="{{ route('studio.previews.link', $entry->id) }}">
                                                @csrf
                                                <button type="submit" class="studio-btn-secondary" title="The link you sent stops working">Replace link</button>
                                            </form>
                                            <form method="post" action="{{ route('studio.previews.password', $entry->id) }}">
                                                @csrf
                                                <button type="submit" class="studio-btn-secondary">New password</button>
                                            </form>
                                        </div>
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
