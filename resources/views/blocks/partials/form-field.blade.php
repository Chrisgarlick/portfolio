{{--
    One text, email or textarea field of a site form, in kit's field markup.
    The error is words under the field (kit-field-error, prefixed "!"), tied
    to it with aria-describedby, so it never relies on colour.

    $form (slug), $name, $field (its definition), $bag (MessageBag).
--}}
@php
    $id = "{$form}-{$name}";
    $type = $field['type'] ?? 'text';
    $required = in_array('required', $field['rules'] ?? [], true);
    $error = $bag->first($name);
    $describedBy = trim(($error ? "{$id}-error " : '').(filled($field['help'] ?? null) ? "{$id}-help" : ''));
    $autocomplete = $field['autocomplete'] ?? match ($name) { 'name' => 'name', 'email' => 'email', 'company' => 'organization', 'website' => 'url', default => null };
@endphp
<div class="kit-field site-form-field" @if ($type === 'textarea') data-wide @endif>
    <label class="kit-field-label" for="{{ $id }}">
        {{ $field['label'] }}@unless ($required) <span class="kit-field-optional">(optional)</span>@endunless
    </label>
    @if ($type === 'textarea')
        <textarea class="kit-input site-form-textarea" id="{{ $id }}" name="{{ $name }}" rows="5"
            @if (filled($field['placeholder'] ?? null)) placeholder="{{ $field['placeholder'] }}" @endif
            @if ($required) required aria-required="true" @endif
            @if ($error) aria-invalid="true" @endif
            @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif>{{ old($name) }}</textarea>
    @else
        <input class="kit-input" id="{{ $id }}" name="{{ $name }}" type="{{ $type === 'email' ? 'email' : 'text' }}" value="{{ old($name) }}"
            @if ($type === 'email') inputmode="email" spellcheck="false" @endif
            @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
            @if (filled($field['placeholder'] ?? null)) placeholder="{{ $field['placeholder'] }}" @endif
            @if ($required) required aria-required="true" @endif
            @if ($error) aria-invalid="true" @endif
            @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif>
    @endif
    @if (filled($field['help'] ?? null))
        <p class="kit-field-hint" id="{{ $id }}-help">{{ $field['help'] }}</p>
    @endif
    @if ($error)
        <p class="kit-field-error" id="{{ $id }}-error">{{ $error }}</p>
    @endif
</div>
