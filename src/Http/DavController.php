<?php

namespace Bambamboole\LaravelDav\Http;

use Bambamboole\LaravelDav\Server\ResponseEmitter;
use Bambamboole\LaravelDav\Server\ServerFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DavController
{
    public function __invoke(Request $request, ServerFactory $servers, ResponseEmitter $responses): Response
    {
        return $responses->toLaravelResponse($servers->create(), $request);
    }
}
