{{--
    Ported from blocks/ContactForm.astro, against the Laravel form definitions
    in app/Cms/forms.php rather than Kritano's form API.

    No @csrf: the page is served from the page cache with no session. The
    FormGuard fields (a rotating path-bound HMAC and the render time) and the
    honeypot do that job, exactly as in partials/form.blade.php.

    resources/js/site/forms.js submits in the background and swaps in the
    success message; the data-* attributes here are its contract. Without
    JavaScript the form still posts normally.
--}}
@php
    $slug = filled($data['form_slug'] ?? null) ? $data['form_slug'] : 'contact';
    $definition = config("cg-forms.{$slug}");
@endphp

@if (is_array($definition))
    @php
        $hidden = app(Cg\Cms\Forms\FormGuard::class)->hiddenFields($slug);
        $isRequired = fn (array $field): bool => in_array('required', $field['rules'] ?? [], true);
    @endphp

    <section class="mx-auto max-w-[1200px] px-6 pb-20 md:pb-24">
        <div class="grid gap-8 border-t-4 border-double border-text-primary pt-8 md:grid-cols-[minmax(0,1fr)_minmax(0,1.6fr)] md:gap-14">
        <div>
            <p class="mb-3 text-[13px] font-semibold tracking-[0.08em] text-accent uppercase">{{ filled($data['label'] ?? null) ? $data['label'] : 'Message' }}</p>
            <h2 class="text-[34px] leading-[1.05] md:text-[44px]">{{ filled($data['heading'] ?? null) ? $data['heading'] : 'Tell me what you need' }}</h2>
        </div>
        <div class="cms-form-container" style="max-width: none; margin: 0; padding: 0;" data-cms-form-container>
            <form method="POST" action="/forms/{{ $slug }}" class="cms-form" novalidate data-cms-form>
                @foreach ($hidden as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach

                {{-- Visually hidden, not display:none. Some bots skip the latter. --}}
                <div class="hp" aria-hidden="true">
                    <label for="{{ Cg\Cms\Forms\FormGuard::HONEYPOT }}">Website</label>
                    <input type="text" tabindex="-1" autocomplete="off"
                           id="{{ Cg\Cms\Forms\FormGuard::HONEYPOT }}"
                           name="{{ Cg\Cms\Forms\FormGuard::HONEYPOT }}">
                </div>

                @foreach ($definition['fields'] ?? [] as $name => $field)
                    @php
                        $type = $field['type'] ?? 'text';
                        $required = $isRequired($field);
                        $id = "cf-{$slug}-{$name}";
                    @endphp

                    @if ($type === 'checkbox')
                        <div class="cms-form-group">
                            <label class="cms-form-checkbox">
                                <input type="checkbox" name="{{ $name }}" value="1" @if ($required) required @endif>
                                <span>{{ $field['label'] }}</span>
                            </label>
                            <p class="cms-form-error" data-error="{{ $name }}">{{ $field['label'] }} is required.</p>
                        </div>
                    @else
                        <div class="cms-form-group">
                            <label for="{{ $id }}" class="cms-form-label">
                                {{ $field['label'] }} @if ($required)<span class="cms-form-required">*</span>@endif
                            </label>

                            @if ($type === 'textarea')
                                <textarea id="{{ $id }}" name="{{ $name }}" rows="4" class="cms-form-input"
                                          placeholder="{{ $field['placeholder'] ?? '' }}"
                                          @if ($required) required @endif></textarea>
                            @elseif ($type === 'select')
                                <select id="{{ $id }}" name="{{ $name }}" class="cms-form-input" @if ($required) required @endif>
                                    <option value="">Select...</option>
                                    @foreach ($field['options'] ?? [] as $option)
                                        <option value="{{ $option }}">{{ $option }}</option>
                                    @endforeach
                                </select>
                            @else
                                <input id="{{ $id }}" name="{{ $name }}"
                                       type="{{ match ($type) { 'email' => 'email', 'phone', 'tel' => 'tel', default => 'text' } }}"
                                       class="cms-form-input"
                                       placeholder="{{ $field['placeholder'] ?? '' }}"
                                       @if ($type === 'email') autocomplete="email" @endif
                                       @if ($required) required @endif>
                            @endif

                            @if (filled($field['help'] ?? null))
                                <p class="cms-form-footer">{{ $field['help'] }}</p>
                            @endif

                            <p class="cms-form-error" data-error="{{ $name }}">{{ $field['label'] }} is required.</p>
                        </div>
                    @endif
                @endforeach

                <div class="cms-form-group">
                    <button type="submit" class="cms-form-submit">Send message</button>
                    <p class="cms-form-footer">By submitting, you agree to the <a href="/privacy">privacy policy</a>.</p>
                </div>

                <div class="cms-form-message cms-form-message--error hidden" data-form-error>
                    <p>Something went wrong. Please try again, or email <a href="mailto:chris@chrisgarlick.com">chris@chrisgarlick.com</a> directly.</p>
                </div>

                <noscript>
                    <p class="cms-form-footer">Without JavaScript the form still sends, and the page reloads afterwards. You can also email <a href="mailto:chris@chrisgarlick.com">chris@chrisgarlick.com</a> directly.</p>
                </noscript>
            </form>

            <div class="cms-form-message cms-form-message--success hidden" data-form-success tabindex="-1" role="status">
                <p>{{ $definition['success'] ?? 'Thank you. Your submission has been received.' }}</p>
            </div>
        </div>
        </div>
    </section>
@else
    <section class="mx-auto max-w-[1200px] px-6 py-20">
        <div class="cms-form-container" style="text-align: center;">
            <p style="color: var(--color-text-secondary);">Form not found. Create a form with slug "{{ $slug }}" in app/Cms/forms.php.</p>
        </div>
    </section>
@endif
