<?php

declare(strict_types=1);

use App\Http\Controllers\Studio\AuditReviewController;
use App\Http\Controllers\Studio\PreviewController;
use Illuminate\Support\Facades\Route;

/*
|------------------------------------------------------------------------------
| Studio: internal tools specific to this site
|------------------------------------------------------------------------------
|
| Behind the CMS admin's own session and sign-in, so there is one login for
| everything rather than the live site's shared ADMIN_SECRET pasted into a
| browser. Loaded with the admin middleware stack in bootstrap/app.php.
|
*/

Route::get('/audits', [AuditReviewController::class, 'index'])->name('index');
Route::get('/audits/{submission}', [AuditReviewController::class, 'show'])->whereUuid('submission')->name('show');
Route::patch('/audits/{submission}', [AuditReviewController::class, 'update'])->whereUuid('submission')->name('update');
Route::post('/audits/{submission}/render', [AuditReviewController::class, 'render'])->whereUuid('submission')->name('render');
Route::get('/audits/{submission}/pdf', [AuditReviewController::class, 'pdf'])->whereUuid('submission')->name('pdf');
Route::post('/audits/{submission}/delete-link', [AuditReviewController::class, 'deleteLink'])->whereUuid('submission')->name('delete-link');

Route::get('/previews', [PreviewController::class, 'index'])->name('previews.index');
Route::post('/previews/{entry}/link', [PreviewController::class, 'newLink'])->whereNumber('entry')->name('previews.link');
Route::post('/previews/{entry}/password', [PreviewController::class, 'newPassword'])->whereNumber('entry')->name('previews.password');
