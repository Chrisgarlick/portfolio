{{-- The preview host's own page (PreviewGateController::page): plain, no scripts, no outside requests. --}}
@php
    $message = match ($state) {
        'home' => 'This is a private website preview. Open the link you were sent to see it.',
        'link' => "This link isn't working any more: it may have been replaced or switched off. Use your password, or ask Chris for a new link.",
        'wrong' => "That password isn't right. Check it and try again.",
        'throttled' => 'Too many tries. Wait a few minutes, then try again.',
        'empty' => "Your preview is nearly ready. Chris will let you know when it's up.",
        default => 'Enter the password Chris sent you to see your website preview.',
    };
    $form = $slug !== null && in_array($state, ['login', 'link', 'wrong'], true);
@endphp
<!doctype html>
<html lang="en-GB">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow, noarchive">
<meta name="referrer" content="no-referrer">
<title>Website preview</title>
<link rel="stylesheet" href="/_preview.css">
</head>
<body>
<main class="gate">
    <p class="gate-from">Chris Garlick</p>
    <h1>Website preview</h1>
    <p class="gate-message @if (in_array($state, ['link', 'wrong', 'throttled'], true)) is-error @endif" @if ($state !== 'login' && $state !== 'home') role="alert" @endif>{{ $message }}</p>
    @if ($form)
        <form method="post" action="/{{ $slug }}/login" class="gate-form">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" autocapitalize="none" spellcheck="false" required @if ($state !== 'home') autofocus @endif>
            <button type="submit">View preview</button>
        </form>
    @endif
    @if (filled($email = config('cg-cms.site.contact_email')))
        <p class="gate-help">Something not working? Email <a href="mailto:{{ $email }}">{{ $email }}</a>.</p>
    @endif
</main>
</body>
</html>
