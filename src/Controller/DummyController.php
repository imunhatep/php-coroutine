<?php
namespace App\Controller;

use GuzzleHttp\Psr7\BufferStream;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class DummyController
{
    function indexAction(ServerRequestInterface $request): ResponseInterface
    {
        $body = new BufferStream;
        $body->write('<html><body>');
        $body->write('<strong>Hello World!</strong>');
        $body->write('<br/><br/>');
        $body->write('Received attributes: <br>' . htmlentities(print_r($request->getAttributes(), true)));
        $body->write('</body></html>');

        return (new Response)
            ->withHeader('Content-Type', 'text/html')
            ->withBody($body);
    }
}
