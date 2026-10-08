{{--
    A public form on a page served from the on-disk cache.

    No @csrf. There is no session on public routes, and a token tied to one
    would be meaningless in HTML rendered weeks ago. See Cg\Cms\Forms\FormToken.

    The hidden fields come from FormGuard: a rotating path-bound HMAC and the
    render timestamp. The honeypot is hidden from people, not from bots.
--}}
@php
    $definition = config("cg-forms.{$form}");
    $hidden = app(Cg\Cms\Forms\FormGuard::class)->hiddenFields($form);
@endphp

@if ($definition)
    <section class="form-block" id="enquire">
        @if (session('form_success'))
            <p class="form-success" role="status">{{ session('form_success') }}</p>
        @else
            @if (!empty($heading))
                <h2>{{ $heading }}</h2>
            @endif

            <form method="POST" action="/forms/{{ $form }}">
                @foreach ($hidden as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach

                @if (!empty($context))
                    <input type="hidden" name="context" value="{{ $context }}">
                @endif

                {{-- Visually hidden, not display:none. Some bots skip the latter. --}}
                <div class="hp" aria-hidden="true">
                    <label for="{{ Cg\Cms\Forms\FormGuard::HONEYPOT }}">Website</label>
                    <input type="text" tabindex="-1" autocomplete="off"
                           id="{{ Cg\Cms\Forms\FormGuard::HONEYPOT }}"
                           name="{{ Cg\Cms\Forms\FormGuard::HONEYPOT }}">
                </div>

                @foreach ($definition['fields'] as $name => $field)
                    <p class="field">
                        <label for="f-{{ $name }}">{{ $field['label'] }}</label>

                        @if ($field['type'] === 'textarea')
                            <textarea id="f-{{ $name }}" name="{{ $name }}" rows="6"
                                @if (in_array('required', $field['rules'] ?? [], true)) required @endif
                            >{{ old($name) }}</textarea>
                        @else
                            <input id="f-{{ $name }}" name="{{ $name }}"
                                   type="{{ $field['type'] === 'email' ? 'email' : 'text' }}"
                                   value="{{ old($name) }}"
                                   @if ($field['type'] === 'email') autocomplete="email" @endif
                                   @if (in_array('required', $field['rules'] ?? [], true)) required @endif>
                        @endif

                        @if (!empty($field['help']))
                            <span class="help">{{ $field['help'] }}</span>
                        @endif

                        @error($name)
                            <span class="error" role="alert">{{ $message }}</span>
                        @enderror
                    </p>
                @endforeach

                <button type="submit">Send</button>
            </form>
        @endif
    </section>
@endif
