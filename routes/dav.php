<?php

use Bambamboole\LaravelDav\Facades\Dav;
use Bambamboole\LaravelDav\Http\DavController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

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

Route::match($davMethods, '/.well-known/caldav', fn(): RedirectResponse => redirect(Dav::baseUri(), 301));
Route::match($davMethods, '/.well-known/carddav', fn(): RedirectResponse => redirect(Dav::baseUri(), 301));

Route::match($davMethods, Dav::baseUri() . '{path?}', DavController::class)->where('path', '.*');
