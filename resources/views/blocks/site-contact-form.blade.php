{{--
    The `site-contact-form` block: the /contact form (the `contact`
    definition in app/Cms/forms.php) between kit's sections.

    It posts to /contact and works without JavaScript: the answer is this page
    again, with the thanks where the form was, or with each error in words
    under its field and the answers kept. No CSRF token: the page has no
    session. FormGuard's token, timing check and honeypot do that job.
--}}
@php
    $form = 'contact';
    $definition = (array) config("cg-forms.{$form}");
    $fields = (array) ($definition['fields'] ?? []);
    $bag = isset($errors) ? $errors->getBag('default') : new Illuminate\Support\MessageBag;
    $success = ($formSuccess ?? [])[$form] ?? null;
    $guardFields = app(Cg\Cms\Forms\FormGuard::class)->hiddenFields($form);
@endphp
<section class="site-form-band" id="message" aria-label="Send me a message">
    <div class="kit-container">
        @if ($success)
            <div class="site-form-done" role="status" tabindex="-1" autofocus>
                <h2 class="site-form-step-title">{!! e($data['success_title'] ?? 'MESSAGE') !!} <em>sent.</em></h2>
                <p class="site-form-step-lede">{{ $success }}</p>
            </div>
        @else
            <form class="site-form" name="contact" method="POST" action="{{ route('contact.submit', [], false) }}" data-form-source>
                @foreach ($guardFields as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach
                @foreach (['landing', 'referrer', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $key)
                    <input type="hidden" name="source[{{ $key }}]" value="" data-source="{{ $key }}">
                @endforeach
                <div class="hp" aria-hidden="true">
                    <label for="contact-hp">Leave this empty</label>
                    <input type="text" id="contact-hp" name="{{ Cg\Cms\Forms\FormGuard::HONEYPOT }}" tabindex="-1" autocomplete="off">
                </div>

                @if ($bag->any())
                    <div class="site-form-summary" role="alert" tabindex="-1" autofocus>
                        <h2 class="site-form-summary-title">Your message didn't send. {{ $bag->count() === 1 ? 'One thing needs' : $bag->count().' things need' }} a look:</h2>
                        <ul>
                            @foreach ($bag->keys() as $name)
                                <li><a href="#{{ $form }}-{{ $name }}">{{ $bag->first($name) }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="site-form-step" data-single>
                    <div class="site-form-step-legend">
                        <span class="site-form-step-kicker">{{ $data['kicker'] ?? 'A message' }}</span>
                        <h2 class="site-form-step-title">{!! preg_replace('/\*(.+?)\*/', '<em>$1</em>', e($data['title'] ?? 'SAY *hello*')) !!}</h2>
                    </div>
                    <p class="site-form-step-lede">{{ $data['lede'] ?? 'A question, a note about something I wrote, or anything else.' }}</p>
                    <div class="site-form-step-fields">
                        @foreach ($fields as $name => $field)
                            @include('blocks.partials.form-field', ['form' => $form, 'name' => $name, 'field' => $field, 'bag' => $bag])
                        @endforeach
                        <div class="site-form-send">
                            <p class="site-form-note">{{ $data['note'] ?? "I'll use these details to reply to you and for nothing else." }} <a href="/privacy">How I handle your data</a>.</p>
                            <button type="submit" class="kit-button site-form-submit">{{ $data['button'] ?? 'Send message' }} <span aria-hidden="true">→</span></button>
                        </div>
                    </div>
                </div>
            </form>
        @endif
    </div>
</section>
