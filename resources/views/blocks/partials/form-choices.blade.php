{{--
    A one-of-several choice of a site form, as chips: real radio buttons in a
    fieldset with its own legend, so the group is named and the arrow keys
    move through it. The selected chip is filled and ticked, never told apart
    by colour alone.

    $form (slug), $name, $field (its definition), $bag (MessageBag).
--}}
@php
    $id = "{$form}-{$name}";
    $required = in_array('required', $field['rules'] ?? [], true);
    $error = $bag->first($name);
    $chosen = old($name);
@endphp
<fieldset class="site-form-choices" @if ($error) aria-describedby="{{ $id }}-error" @endif>
    <legend class="kit-field-label">
        {{ $field['label'] }}@unless ($required) <span class="kit-field-optional">(optional)</span>@endunless
    </legend>
    <div class="site-chips">
        @foreach ($field['options'] ?? [] as $i => $option)
            <label class="site-chip">
                <input type="radio" name="{{ $name }}" value="{{ $option }}" id="{{ $id }}-{{ $i }}"
                    @checked($chosen === $option)
                    @if ($required) required @endif
                    @if ($error) aria-invalid="true" @endif>
                <span class="site-chip-text">{{ $option }}</span>
            </label>
        @endforeach
    </div>
    @if ($error)
        <p class="kit-field-error" id="{{ $id }}-error">{{ $error }}</p>
    @endif
</fieldset>
