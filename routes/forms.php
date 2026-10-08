<?php

declare(strict_types=1);

use App\Http\Controllers\FormController;
use Illuminate\Support\Facades\Route;

/*
|------------------------------------------------------------------------------
| Form posts
|------------------------------------------------------------------------------
|
| Separate from public.php because these need a session and public GETs must
| not have one. A POST is never cached, so a session here costs nothing.
|
| The `form` group deliberately omits VerifyCsrfToken. There is no session on
| the page that rendered the form, so there is no token to match. Its job is
| done by FormToken (a rotating path-bound HMAC) plus FormGuard (honeypot,
| time-to-submit, rate limit). See the note in Cg\Cms\Forms\FormToken about
| why session CSRF is close to meaningless for an unauthenticated form whose
| only effect is sending an email.
|
| Consequence worth knowing: submitting a form gives that visitor a session
| cookie, and the nginx page-cache bypass keys on that cookie, so their
| subsequent page views hit PHP instead of the cache. That is what lets them
| see the success message on a page that would otherwise be served from disk.
| It applies to one visitor who just became a lead, not to anonymous traffic.
|
*/

Route::post('/forms/{slug}', [FormController::class, 'store'])
    ->middleware('form')
    ->name('forms.store');
