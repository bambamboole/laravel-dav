<?php

namespace Bambamboole\LaravelDav\Server;

use DOMDocument;
use Illuminate\Http\Request as LaravelRequest;
use Illuminate\Http\Response as LaravelResponse;
use Sabre\DAV\Exception as SabreException;
use Sabre\DAV\Server;
use Sabre\DAV\Version;
use Sabre\HTTP\Request as SabreRequest;
use Sabre\HTTP\Response as SabreResponse;
use Throwable;

class ResponseEmitter
{
    public function toLaravelResponse(Server $server, LaravelRequest $request): LaravelResponse
    {
        $sabreRequest = $this->toSabreRequest($request);
        $sabreResponse = new SabreResponse;

        $sabreRequest->setBaseUrl($server->getBaseUri());
        $server->httpRequest = $sabreRequest;
        $server->httpResponse = $sabreResponse;

        try {
            $server->invokeMethod($sabreRequest, $sabreResponse, false);
        } catch (Throwable $throwable) {
            $this->handleException($server, $throwable);
            $this->writeExceptionResponse($server, $sabreResponse, $throwable);
        }

        return $this->toLaravelResponseFromSabre($sabreResponse);
    }

    private function toSabreRequest(LaravelRequest $request): SabreRequest
    {
        return new SabreRequest(
            $request->method(),
            $request->getRequestUri(),
            $this->headers($request),
            $request->getContent(),
        );
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    private function headers(LaravelRequest $request): array
    {
        $headers = $request->headers->all();
        $authorization = $request->headers->get('Authorization')
            ?? $request->server->get('HTTP_AUTHORIZATION')
            ?? $request->server->get('REDIRECT_HTTP_AUTHORIZATION');

        if (! $authorization && $request->getUser() !== null) {
            $authorization = 'Basic '.base64_encode($request->getUser().':'.$request->getPassword());
        }

        if ($authorization) {
            $headers['Authorization'] = $authorization;
        }

        return $headers;
    }

    private function handleException(Server $server, Throwable $throwable): void
    {
        try {
            $server->emit('exception', [$throwable]);
        } catch (Throwable) {
            //
        }

        if (! $throwable instanceof SabreException) {
            report($throwable);
        }
    }

    private function writeExceptionResponse(Server $server, SabreResponse $response, Throwable $throwable): void
    {
        $document = new DOMDocument('1.0', 'utf-8');
        $document->formatOutput = true;

        $error = $document->createElementNS('DAV:', 'd:error');
        $error->setAttribute('xmlns:s', Server::NS_SABREDAV);
        $document->appendChild($error);

        if (Server::$exposeVersion) {
            $error->appendChild($document->createElement('s:sabredav-version', $this->escape(Version::VERSION)));
        }

        $error->appendChild($document->createElement('s:exception', $this->escape($this->exceptionClass($server, $throwable))));
        $error->appendChild($document->createElement('s:message', $this->escape($this->exceptionMessage($server, $throwable))));

        if ($server->debugExceptions) {
            $this->appendDebugExceptionDetails($document, $error, $throwable);
        }

        $status = 500;
        $headers = $response->getHeaders();

        if ($throwable instanceof SabreException) {
            $status = $throwable->getHTTPCode();
            $throwable->serialize($server, $error);
            $headers = array_replace($headers, $throwable->getHTTPHeaders($server));
        }

        $headers['Content-Type'] = 'application/xml; charset=utf-8';

        $response->setStatus($status);
        $response->setHeaders($headers);
        $response->setBody($document->saveXML());
    }

    private function appendDebugExceptionDetails(DOMDocument $document, \DOMElement $error, Throwable $throwable): void
    {
        $error->appendChild($document->createElement('s:file', $this->escape($throwable->getFile())));
        $error->appendChild($document->createElement('s:line', $this->escape($throwable->getLine())));
        $error->appendChild($document->createElement('s:code', $this->escape($throwable->getCode())));
        $error->appendChild($document->createElement('s:stacktrace', $this->escape($throwable->getTraceAsString())));

        $previous = $throwable;
        while ($previous = $previous->getPrevious()) {
            $previousElement = $document->createElement('s:previous-exception');
            $previousElement->appendChild($document->createElement('s:exception', $this->escape($previous::class)));
            $previousElement->appendChild($document->createElement('s:message', $this->escape($previous->getMessage())));
            $previousElement->appendChild($document->createElement('s:file', $this->escape($previous->getFile())));
            $previousElement->appendChild($document->createElement('s:line', $this->escape($previous->getLine())));
            $previousElement->appendChild($document->createElement('s:code', $this->escape($previous->getCode())));
            $previousElement->appendChild($document->createElement('s:stacktrace', $this->escape($previous->getTraceAsString())));
            $error->appendChild($previousElement);
        }
    }

    private function exceptionClass(Server $server, Throwable $throwable): string
    {
        return $server->debugExceptions || $throwable instanceof SabreException
            ? $throwable::class
            : SabreException::class;
    }

    private function exceptionMessage(Server $server, Throwable $throwable): string
    {
        return $server->debugExceptions || $throwable instanceof SabreException
            ? $throwable->getMessage()
            : 'Internal server error.';
    }

    private function toLaravelResponseFromSabre(SabreResponse $sabreResponse): LaravelResponse
    {
        $response = response(
            $sabreResponse->getBodyAsString(),
            $sabreResponse->getStatus(),
        );

        foreach ($sabreResponse->getHeaders() as $name => $values) {
            $response->headers->set($name, $values);
        }

        return $response;
    }

    private function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_NOQUOTES, 'UTF-8');
    }
}
