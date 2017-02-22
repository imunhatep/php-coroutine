<?php
namespace App\Controller;

use GuzzleHttp\Psr7\BufferStream;
use GuzzleHttp\Psr7\Response;
use Interop\Http\Middleware\DelegateInterface;
use Interop\Http\Middleware\MiddlewareInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

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

        return $this->indexAction($request, $response);
    }

    protected function indexAction(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = new BufferStream;
        $body->write('<html><body>');
        $body->write('<strong>Hello World!</strong>');
        $body->write('<br/><br/>');
        $body->write('Received attributes: <br>' . htmlentities(print_r($request->getAttributes(), true)));
        $body->write('</body></html>');

        return $response
            ->withHeader('Content-Type', 'text/html')
            ->withBody($body);
    }
}
