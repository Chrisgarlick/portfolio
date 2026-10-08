{{--
    Category filter tabs, shared by the work, tools and resources listings.
    Behaviour in resources/js/site/filters.js; cards opt in with
    data-filter-value. Without JavaScript every card shows, which is the "All"
    tab, so nothing is hidden from anyone.
--}}
@props(['group', 'options', 'label' => 'Filter by category'])

<div class="mb-12 flex flex-wrap gap-2" role="tablist" aria-label="{{ $label }}" data-filter-tabs="{{ $group }}">
    @foreach ($options as $option)
        <button type="button" role="tab" data-filter="{{ $option }}"
                aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                class="border px-3 py-1.5 font-body text-xs font-medium tracking-widest uppercase transition-all duration-200 {{ $loop->first ? 'border-accent bg-accent text-white' : 'border-border bg-bg-muted text-text-secondary hover:border-border-hover hover:text-text-primary' }}"
                style="border-radius: 3px;">
            {{ $option }}
        </button>
    @endforeach
</div>
