<?php

use Bambamboole\LaravelDav\Server\ResponseEmitter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Sabre\DAV\Server;
use Sabre\DAV\SimpleCollection;

it('hides non-sabre exception details outside debug mode', function (): void {
    $response = emitThrowingDavResponse(false);

    expect($response->getStatusCode())->toBe(500)
        ->and($response->getContent())->toContain('<s:exception>Sabre\DAV\Exception</s:exception>')
        ->and($response->getContent())->toContain('<s:message>Internal server error.</s:message>')
        ->and($response->getContent())->not->toContain('Exploded while handling DAV request')
        ->and($response->getContent())->not->toContain('<s:stacktrace>');
});

it('exposes non-sabre exception details in debug mode', function (): void {
    $response = emitThrowingDavResponse(true);

    expect($response->getStatusCode())->toBe(500)
        ->and($response->getContent())->toContain('<s:exception>RuntimeException</s:exception>')
        ->and($response->getContent())->toContain('<s:message>Exploded while handling DAV request</s:message>')
        ->and($response->getContent())->toContain('<s:stacktrace>');
});

function emitThrowingDavResponse(bool $debug): Response
{
    $server = new Server(new SimpleCollection('root'));
    $server->setBaseUri('/dav/');
    $server->debugExceptions = $debug;
    $server->on('beforeMethod:PROPFIND', static function (): void {
        throw new RuntimeException('Exploded while handling DAV request');
    });

    return (new ResponseEmitter)->toLaravelResponse(
        $server,
        Request::create('/dav/', 'PROPFIND'),
    );
}
