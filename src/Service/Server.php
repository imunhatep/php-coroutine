<?php
namespace App\Service;

use App\Entity\KernelCall;
use App\Entity\ProcessInterface;
use App\Service\Kernel\KernelInterface;
use Collection\Map;
use Collection\Sequence;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use mindplay\middleman\Dispatcher;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RingCentral\Psr7;

class Server
{
    const MTU_SIZE = 1500; // default MTU packet size

    /** @var string */
    private $host;

    /** @var int */
    private $port;

    /** @var Dispatcher */
    private $dispatcher;

    function __construct(string $host, int $port, Dispatcher $dispatcher)
    {
        $this->host = $host;
        $this->port = $port;
        $this->dispatcher = $dispatcher;
    }

    function __invoke(): \Generator
    {
        dump("Starting server at: http://{$this->host}:{$this->port}");

        $socket = @stream_socket_server("tcp://{$this->host}:{$this->port}", $errNo, $errStr);
        if (!$socket) {
            throw new \Exception($errStr, $errNo);
        }

        stream_set_blocking($socket, 0);

        while (true) {
            yield $this->waitIoRead($socket);

            $clientSocket = stream_socket_accept($socket, 0);
            yield KernelCall::callback($this->handleClient($clientSocket));
        }
    }

    protected function waitIoRead($socket): KernelCall
    {
        return new KernelCall(
            function (ProcessInterface $task, KernelInterface $scheduler) use ($socket) {
                $scheduler->handleIoRead($socket, $task);
            }
        );
    }

    protected function waitIoWrite($socket): KernelCall
    {
        return new KernelCall(
            function (ProcessInterface $task, KernelInterface $scheduler) use ($socket) {
                $scheduler->handleIoWrite($socket, $task);
            }
        );
    }

    protected function handleClient($socket): \Generator
    {
        yield $this->waitIoRead($socket);

        try{
            /** @var ServerRequestInterface $request */
            $request = $this->getServerRequest($this->readFromSocket($socket));

            /** @var ResponseInterface $response */
            $response = $this->dispatcher->dispatch($request);
        }
        catch(\Throwable $e){
            /** @var ResponseInterface $response */
            $response = new Response(500, ['Content-Type' => 'text/html'], $e->getMessage());
        }

        yield $this->waitIoWrite($socket);

        $this->sendResponse($socket, $response);

        stream_socket_shutdown($socket, STREAM_SHUT_RDWR);
        fclose($socket);
    }

    protected function sendResponse($socket, ResponseInterface $response)
    {
        $rawBody = (string)$response->getBody()."\n";

        $httpHeader = sprintf(
            "HTTP/%s %d %s\r\n",
            $response->getProtocolVersion(),
            $response->getStatusCode(),
            $response->getReasonPhrase()
        );

        $rawHeaders = (new Map)
            ->setAll(
                $response
                    ->withHeader('Content-Length', strlen($rawBody))
                    ->withHeader('Connection', 'close')
                    ->getHeaders()
            )
            ->foldLeft(
                $httpHeader,
                function (string $memo, string $name, array $value): string {
                    return $memo.ucfirst($name).': '.implode(';', $value)."\r\n";
                }
            )
        ;

        if($socket) {
            @stream_socket_sendto($socket, trim($rawHeaders, "\n\r")."\r\n\r\n");
            @stream_socket_sendto($socket, $rawBody);
        }
    }

    protected function readFromSocket($socket): string
    {
        $contents = '';
        while (!feof($socket)) {
            $buf = stream_socket_recvfrom($socket, self::MTU_SIZE);
            $contents .= $buf;

            if (strlen($buf) < self::MTU_SIZE) {
                break;
            }
        }

        return $contents;
    }

    /**
     * @param string $rawRequest
     *
     * @return ServerRequestInterface
     */
    protected function getServerRequest(string $rawRequest): ServerRequestInterface
    {
        $request = Psr7\parse_request($rawRequest);

        $headers = array_map(
            function ($val) {
                if (1 === count($val)) {
                    $val = $val[0];
                }

                return $val;
            },
            $request->getHeaders()
        );

        $serverRequest = new ServerRequest(
            $request->getMethod(),
            $request->getUri(),
            $headers,
            $request->getBody(),
            $request->getProtocolVersion(),
            $_SERVER
        );

        $serverRequest = (new Sequence)
            ->addAll(explode('&', $request->getUri()->getQuery()))
            ->filter(
                function(string $keyValuePair): bool
                {
                    return trim($keyValuePair) !== '';
                }
            )
            ->foldLeft(
                $serverRequest,
                function(ServerRequestInterface $memo, string $keyValuePair): ServerRequestInterface
                {
                    list($name, $value) = explode('=', $keyValuePair);
                    return $memo->withAttribute($name, $value);
                }
            );

        return $serverRequest;
    }
}
