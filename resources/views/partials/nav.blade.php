{{--
    Main navigation: the magazine masthead. Links come from config('site.nav');
    a child link with an `accent` previews its topic colour.

    The active state is decided by the request path at render time. That is
    safe to cache: the page cache keys on the path, so every cached copy of a
    page carries its own correct active link.
--}}
@php
    $currentPath = '/'.ltrim(request()->getPathInfo(), '/');
    $isActive = fn (string $href): bool => $href === '/' ? $currentPath === '/' : str_starts_with($currentPath, $href);
@endphp

<a href="#main" class="skip-to-content">Skip to content</a>

<nav class="fixed top-0 right-0 left-0 z-50 h-16 border-b border-text-primary"
     style="background: rgba(250,248,243,0.94); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);"
     aria-label="Main navigation">
    <div class="mx-auto flex h-full max-w-[1200px] items-center justify-between gap-6 px-6">
        <a href="/" class="font-display text-[30px] leading-none tracking-tight text-text-primary no-underline" aria-label="Chris Garlick, home">
            Chris Garlick
        </a>

        <div class="hidden items-center gap-7 md:flex">
            @foreach (config('site.nav') as $link)
                @php($linkClasses = 'nav-link text-[14px] font-medium no-underline transition-colors duration-150 '.($isActive($link['href']) ? 'text-text-primary active' : 'text-text-secondary hover:text-text-primary'))

                @if (! empty($link['children']))
                    <div class="nav-dropdown relative">
                        <a href="{{ $link['href'] }}" class="{{ $linkClasses }}" aria-haspopup="true">
                            {{ $link['label'] }}
                            <svg class="ml-1 inline-block h-3 w-3 opacity-50" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M3 5l3 3 3-3"/></svg>
                        </a>
                        <div class="nav-dropdown-menu absolute top-full left-0 hidden pt-3">
                            <div class="min-w-[240px] rounded-md border border-border bg-bg-surface py-2 shadow-lg">
                                @foreach ($link['children'] as $child)
                                    <a href="{{ $child['href'] }}"
                                       @if (! empty($child['accent'])) data-accent="{{ $child['accent'] }}" @endif
                                       class="flex items-center gap-2.5 px-4 py-2 text-[14px] no-underline transition-colors duration-150 {{ $currentPath === $child['href'] ? 'text-accent' : 'text-text-secondary hover:bg-bg-muted hover:text-accent' }}">
                                        @if (! empty($child['accent']))<span class="h-1.5 w-1.5 rounded-full bg-accent" aria-hidden="true"></span>@endif
                                        {{ $child['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @else
                    <a href="{{ $link['href'] }}" class="{{ $linkClasses }}">{{ $link['label'] }}</a>
                @endif
            @endforeach

            <a href="/contact"
               class="inline-block rounded-full bg-text-primary px-5 py-2.5 text-[14px] font-semibold text-bg-primary no-underline transition-opacity duration-150 hover:text-bg-primary hover:opacity-85">
                Start a project
            </a>
        </div>

        <button id="nav-toggle" type="button" class="flex h-11 w-11 items-center justify-center text-text-primary md:hidden" aria-label="Open menu" aria-expanded="false" aria-controls="nav-drawer">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
                <line x1="4" y1="7" x2="20" y2="7"/>
                <line x1="4" y1="17" x2="20" y2="17"/>
            </svg>
        </button>
    </div>
</nav>

<div id="nav-drawer" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-label="Navigation menu">
    <div id="nav-overlay" class="absolute inset-0" style="background: rgba(26,23,21,0.3);"></div>

    <div class="absolute top-0 right-0 flex h-full w-72 flex-col overflow-y-auto bg-bg-surface p-8 pt-20 shadow-2xl">
        <button id="nav-close" type="button" class="absolute top-4 right-5 flex h-11 w-11 items-center justify-center text-text-secondary hover:text-text-primary" aria-label="Close menu">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
                <line x1="6" y1="6" x2="18" y2="18"/>
                <line x1="18" y1="6" x2="6" y2="18"/>
            </svg>
        </button>

        <div class="flex flex-col gap-5">
            @foreach (config('site.nav') as $link)
                <div>
                    <a href="{{ $link['href'] }}"
                       class="font-display text-[28px] leading-tight no-underline {{ $isActive($link['href']) ? 'text-accent' : 'text-text-secondary hover:text-text-primary' }}">
                        {{ $link['label'] }}
                    </a>
                    @if (! empty($link['children']))
                        <div class="mt-2 ml-4 flex flex-col gap-2">
                            @foreach ($link['children'] as $child)
                                <a href="{{ $child['href'] }}"
                                   @if (! empty($child['accent'])) data-accent="{{ $child['accent'] }}" @endif
                                   class="text-[14px] no-underline {{ $currentPath === $child['href'] ? 'text-accent' : 'text-text-tertiary hover:text-accent' }}">
                                    {{ $child['label'] }}
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="mt-auto pt-8">
            <a href="/contact"
               class="inline-block rounded-full bg-text-primary px-6 py-3 text-[15px] font-semibold text-bg-primary no-underline hover:text-bg-primary hover:opacity-85">
                Start a project
            </a>
        </div>
    </div>
</div>
