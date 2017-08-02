<?php
namespace App\Request;

use Collection\Sequence;
use GuzzleHttp\Psr7\ServerRequest;
use Interop\Http\Middleware\DelegateInterface;
use Interop\Http\Middleware\MiddlewareInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ServerRequestWrapper implements MiddlewareInterface
{

    public function process(RequestInterface $request, DelegateInterface $delegate): ResponseInterface
    {
        $serverRequest = $this->getServerRequest($request);

        return $delegate->process($serverRequest);
    }

    protected function getServerRequest(RequestInterface $request): ServerRequestInterface
    {
        $headers = array_map(
            function ($val) {
                if (1 === count($val)) {
                    $val = $val[0];
                }

                return $val;
            },
            $request->getHeaders()
        );

        /** @var ServerRequestInterface $serverRequest */
        $serverRequest = new ServerRequest(
            $request->getMethod(),
            $request->getUri(),
            $headers,
            $request->getBody(),
            $request->getProtocolVersion(),
            $_SERVER
        );

        $serverRequest = (new Sequence())
            ->addAll(explode('&', $request->getUri()->getQuery()))
            ->foldLeft(
                $serverRequest,
                function(ServerRequestInterface $memo, string $keyValuePair): ServerRequestInterface
                {
                    list($name, $value) = explode('=', $keyValuePair, 2) + [null,null];
                    return $memo->withAttribute($name, $value);
                }
            );

        dump($serverRequest->getAttributes());
        return $serverRequest;
    }
}
