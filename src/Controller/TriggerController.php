<?php
namespace App\Controller;

use GuzzleHttp\Psr7\BufferStream;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Filesystem\Filesystem;

class TriggerController
{
    function indexAction(ServerRequestInterface $request): ResponseInterface
    {
        $this
            ->getLogger()
            ->debug($request->getMethod() . ': ' . (string)$request->getUri())
            ->debug($request->getBody()->getContents())
            ->save();

        $body = new BufferStream;
        $body->write(json_encode('OK'));

        return (new Response)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body);
    }

    private function getLogger()
    {
        return new class
        {
            private $fs;
            private $logs;

            function __construct()
            {
                $this->fs = new Filesystem;
                $this->logs = [];
            }

            function debug(string $msg)
            {
                $this->logs[] = $msg;

                return $this;
            }

            function save()
            {
                $filename = './var/log/' . date('Y-m-d_H:i:s');
                $this->fs->dumpFile($filename, implode("\n", $this->logs));
            }
        };
    }
}
