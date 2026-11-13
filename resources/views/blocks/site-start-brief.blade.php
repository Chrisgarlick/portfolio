{{--
    The `site-start-brief` block: the /start brief (Design nf_start), its
    three steps as three fieldsets on one page, on a paper band between the
    night opener and "Then, a call".

    It posts to /start (FormController, the `start` definition in
    app/Cms/forms.php) and works without JavaScript: a sent brief lands on
    /start/thanks, and a brief with a problem comes back as this page with a
    summary at the top, each error in words under its field, and the answers
    kept. No CSRF token: the page has no session. FormGuard's token, timing
    check and honeypot do that job, as on every public form.

    resources/js/site/form-source.js fills the source fields (the landing
    page, the referrer, UTM tags) when a script runs.
--}}
@php
    $form = 'start';
    $definition = (array) config("cg-forms.{$form}");
    $fields = (array) ($definition['fields'] ?? []);
    $bag = isset($errors) ? $errors->getBag('default') : new Illuminate\Support\MessageBag;
    $guardFields = app(Cg\Cms\Forms\FormGuard::class)->hiddenFields($form);
    $target = fn (string $name): string => ($fields[$name]['type'] ?? '') === 'choice' ? "{$form}-{$name}-0" : "{$form}-{$name}";
    $steps = [
        ['n' => '01', 'title' => $data['step1_title'] ?? 'WHAT DO YOU *need?*', 'lede' => $data['step1_lede'] ?? 'Pick the closest. Not sure is a fine answer.', 'fields' => ['need', 'details']],
        ['n' => '02', 'title' => $data['step2_title'] ?? 'BUDGET AND *timing*', 'lede' => $data['step2_lede'] ?? "A rough band (small, medium or large, to discuss) and when you'd like it live.", 'fields' => ['budget', 'timeline']],
        ['n' => '03', 'title' => $data['step3_title'] ?? 'YOUR *details*', 'lede' => $data['step3_lede'] ?? 'Your name, your email, and anything else I should know.', 'fields' => ['name', 'email', 'company', 'website']],
    ];
    $title = fn (string $text) => new Illuminate\Support\HtmlString(preg_replace('/\*(.+?)\*/', '<em>$1</em>', e($text)));
@endphp
<section class="site-form-band" id="brief" aria-label="Your brief">
    <div class="kit-container">
        <form class="site-form" method="POST" action="{{ route('start.submit', [], false) }}" data-form-source>
            @foreach ($guardFields as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            @foreach (['landing', 'referrer', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $key)
                <input type="hidden" name="source[{{ $key }}]" value="" data-source="{{ $key }}">
            @endforeach
            {{-- Visually hidden, not display:none: some bots skip the latter. --}}
            <div class="hp" aria-hidden="true">
                <label for="start-hp">Leave this empty</label>
                <input type="text" id="start-hp" name="{{ Cg\Cms\Forms\FormGuard::HONEYPOT }}" tabindex="-1" autocomplete="off">
            </div>

            @if ($bag->any())
                <div class="site-form-summary" role="alert" tabindex="-1" autofocus>
                    <h2 class="site-form-summary-title">Your brief didn't send. {{ $bag->count() === 1 ? 'One thing needs' : $bag->count().' things need' }} a look:</h2>
                    <ul>
                        @foreach ($bag->keys() as $name)
                            <li><a href="#{{ $target($name) }}">{{ $bag->first($name) }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @foreach ($steps as $step)
                <fieldset class="site-form-step">
                    <legend class="site-form-step-legend">
                        <span class="site-form-step-kicker">Step {{ $step['n'] }} of 03</span>
                        <h2 class="site-form-step-title">{{ $title($step['title']) }}</h2>
                    </legend>
                    <p class="site-form-step-lede">{{ $step['lede'] }}</p>
                    <div class="site-form-step-fields">
                        @foreach ($step['fields'] as $name)
                            @continue (! isset($fields[$name]))
                            @if (($fields[$name]['type'] ?? '') === 'choice')
                                @include('blocks.partials.form-choices', ['form' => $form, 'name' => $name, 'field' => $fields[$name], 'bag' => $bag])
                            @else
                                @include('blocks.partials.form-field', ['form' => $form, 'name' => $name, 'field' => $fields[$name], 'bag' => $bag])
                            @endif
                        @endforeach
                    </div>
                </fieldset>
            @endforeach

            <div class="site-form-send">
                <p class="site-form-note">{{ $data['note'] ?? "I'll use these details to reply to you and for nothing else." }} <a href="/privacy">How I handle your data</a>.</p>
                <button type="submit" class="kit-button site-form-submit">{{ $data['button'] ?? 'Send my brief' }} <span aria-hidden="true">→</span></button>
            </div>
        </form>
    </div>
</section>
