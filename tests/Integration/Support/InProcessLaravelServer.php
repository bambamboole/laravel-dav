<?php

namespace Bambamboole\LaravelDav\Tests\Integration\Support;

use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\HttpServer;
use Amp\Http\Server\HttpServerStatus;
use Amp\Http\Server\Request as AmpRequest;
use Amp\Http\Server\RequestHandler\ClosureRequestHandler;
use Amp\Http\Server\Response as AmpResponse;
use Amp\Http\Server\SocketHttpServer;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request as LaravelRequest;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Response as LaravelResponse;
use Throwable;

final class InProcessLaravelServer
{
    private ?HttpServer $server = null;

    private ?Throwable $lastThrowable = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
    ) {}

    public function start(): void
    {
        if ($this->server instanceof HttpServer) {
            return;
        }

        $this->server = SocketHttpServer::createForDirectAccess(
            logger: new NullLogger,
            allowedMethods: null,
        );
        $this->server->expose("{$this->host}:{$this->port}");
        $this->server->start(
            new ClosureRequestHandler($this->handleRequest(...)),
            new DefaultErrorHandler,
        );
    }

    public function stop(): void
    {
        if (! $this->server instanceof HttpServer) {
            return;
        }

        if (in_array($this->server->getStatus(), [HttpServerStatus::Starting, HttpServerStatus::Started], true)) {
            $this->server->stop();
        }

        $this->server = null;
    }

    public function url(): string
    {
        return "http://{$this->host}:{$this->port}";
    }

    public function lastThrowable(): ?Throwable
    {
        return $this->lastThrowable;
    }

    private function handleRequest(AmpRequest $request): AmpResponse
    {
        $uri = $request->getUri();
        $path = $uri->getPath() === '' ? '/' : $uri->getPath();
        $query = $uri->getQuery();
        $url = $this->url().$path.($query !== '' ? '?'.$query : '');

        $laravelRequest = LaravelRequest::create(
            $url,
            strtoupper($request->getMethod()),
            [],
            [],
            [],
            [],
            (string) $request->getBody(),
        );
        $laravelRequest->headers->add($request->getHeaders());

        $kernel = app(HttpKernel::class);

        try {
            $laravelResponse = $kernel->handle($laravelRequest);
        } catch (Throwable $throwable) {
            $this->lastThrowable = $throwable;

            throw $throwable;
        }

        $kernel->terminate($laravelRequest, $laravelResponse);

        if (property_exists($laravelResponse, 'exception') && $laravelResponse->exception instanceof Throwable) {
            $this->lastThrowable = $laravelResponse->exception;
        }

        $body = $laravelResponse->getContent() ?: '';

        return new AmpResponse(
            $laravelResponse->getStatusCode(),
            $this->responseHeaders($laravelResponse, $body),
            $body,
        );
    }

    /**
     * @return array<string, list<string>>
     */
    private function responseHeaders(LaravelResponse $response, string $body): array
    {
        $headers = $response->headers->all();

        foreach (['connection', 'keep-alive', 'proxy-authenticate', 'proxy-authorization', 'te', 'trailer', 'transfer-encoding', 'upgrade'] as $header) {
            unset($headers[$header]);
        }

        $headers['content-length'] = [(string) strlen($body)];

        return $headers;
    }
}
