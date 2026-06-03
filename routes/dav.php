<?php

use Bambamboole\LaravelDav\Http\DavController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

$prefix = trim((string) config('dav.route.prefix', 'dav'), '/');

$davMethods = [
    'GET',
    'HEAD',
    'POST',
    'PUT',
    'PATCH',
    'DELETE',
    'OPTIONS',
    'PROPFIND',
    'PROPPATCH',
    'MKCOL',
    'MKCALENDAR',
    'COPY',
    'MOVE',
    'LOCK',
    'UNLOCK',
    'REPORT',
    'ACL',
];

Route::match($davMethods, '/.well-known/caldav', fn (): RedirectResponse => redirect("/{$prefix}/", 301));
Route::match($davMethods, '/.well-known/carddav', fn (): RedirectResponse => redirect("/{$prefix}/", 301));

Route::match($davMethods, "/{$prefix}/{path?}", DavController::class)->where('path', '.*');
