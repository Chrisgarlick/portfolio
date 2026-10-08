{{--
    One field of the audit form, in a fixed step or a sector block. Selects are
    radio groups and multiselects checkbox groups, as on the live page, so every
    option is visible without opening anything. $sector is set inside a sector
    block, which is also what keeps ids unique when two sectors share a name.
--}}
@php
    $id = 'f-'.(isset($sector) ? $sector.'-' : '').$field['name'];
    $required = ! empty($field['required']) ? 'true' : 'false';
    $sectorAttr = isset($sector) ? 'data-sector-field='.$sector : '';
@endphp

<div class="mb-5" data-field-wrap="{{ $field['name'] }}"
     @if (! empty($field['showIf'])) data-show-if="{{ json_encode($field['showIf']) }}" @endif>
    <label for="{{ $id }}" class="audit-label">
        {{ $field['label'] ?? '' }}
        @if (! empty($field['required']))<span class="audit-req">*</span>@endif
        @if (! empty($field['help']))<span class="audit-help">{{ $field['help'] }}</span>@endif
    </label>

    @switch($field['type'])
        @case('textarea')
            <textarea id="{{ $id }}" name="{{ $field['name'] }}" rows="3"
                      @if (! empty($field['maxLength'])) maxlength="{{ $field['maxLength'] }}" @endif
                      class="audit-textarea" data-required="{{ $required }}" data-min-length="{{ $field['minLength'] ?? 0 }}" {{ $sectorAttr }}></textarea>
            @break

        @case('select')
            <div class="audit-options">
                @foreach ($form->options($field) as $option)
                    <label class="audit-option">
                        <input type="radio" name="{{ $field['name'] }}" value="{{ $option['value'] }}" data-required="{{ $required }}" {{ $sectorAttr }}>
                        <span>{{ $option['label'] }}</span>
                    </label>
                @endforeach
            </div>
            @break

        @case('multiselect')
            <div class="audit-options">
                @foreach ($form->options($field) as $option)
                    <label class="audit-option">
                        <input type="checkbox" name="{{ $field['name'] }}" value="{{ $option['value'] }}" {{ $sectorAttr }}>
                        <span>{{ $option['label'] }}</span>
                    </label>
                @endforeach
            </div>
            @break

        @default
            @php
                $type = in_array($field['type'], ['email', 'url'], true) ? $field['type'] : 'text';
                $placeholder = $field['placeholder'] ?? match ($type) { 'email' => 'you@firm.co.uk', 'url' => 'https://', default => '' };
            @endphp
            <input type="{{ $type }}" id="{{ $id }}" name="{{ $field['name'] }}" placeholder="{{ $placeholder }}"
                   @if (! empty($field['maxLength'])) maxlength="{{ $field['maxLength'] }}" @endif
                   @if ($type === 'email') autocomplete="email" @elseif ($type === 'url') autocomplete="url" @endif
                   class="audit-input" data-required="{{ $required }}" data-min-length="{{ $field['minLength'] ?? 0 }}" {{ $sectorAttr }}>
    @endswitch

    <p class="audit-error hidden" data-error-for="{{ $field['name'] }}"></p>
</div>
