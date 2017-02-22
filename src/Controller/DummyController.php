<?php
namespace App\Controller;

use GuzzleHttp\Psr7\BufferStream;
use GuzzleHttp\Psr7\Response;
use Interop\Http\Middleware\DelegateInterface;
use Interop\Http\Middleware\MiddlewareInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class DummyController implements MiddlewareInterface
{
    public function process(RequestInterface $request, DelegateInterface $delegate): ResponseInterface
    {
        try{
            /** @var ResponseInterface $response */
            $response = $delegate->process($request);
        } catch (\LogicException $e) {
            /** @var ResponseInterface $response */
            $response = new Response(200);
        }

        $body = new BufferStream;
        $body->write('<strong>Hello World!</strong>');

        return $response
            ->withHeader('Content-Type', 'text/html')
            ->withBody($body);
    }
}
