<?php
namespace App\Service;

use App\Entity\KernelCall;
use App\Entity\ProcessInterface;
use App\Service\Kernel\KernelInterface;

class Server
{
    const MTU_SIZE = 1500; // default MTU packet size

    private $host;
    private $port;

    function __construct(string $host, int $port)
    {
        $this->host = $host;
        $this->port = $port;
    }

    function __invoke(): \Generator
    {
        dump("Starting server at: {$this->host}:{$this->port}...");

        $socket = @stream_socket_server("tcp://{$this->host}:{$this->port}", $errNo, $errStr);
        if (!$socket) {
            throw new \Exception($errStr, $errNo);
        }

        stream_set_blocking($socket, 0);

        while (true) {
            yield $this->waitIoRead($socket);

            $clientSocket = stream_socket_accept($socket, 0);
            yield newCallback($this->handleClient($clientSocket));
        }
    }

    function waitIoRead($socket): KernelCall
    {
        return new KernelCall(
            function (ProcessInterface $task, KernelInterface $scheduler) use ($socket) {
                $scheduler->handleIoRead($socket, $task);
            }
        );
    }

    function waitIoWrite($socket): KernelCall
    {
        return new KernelCall(
            function (ProcessInterface $task, KernelInterface $scheduler) use ($socket) {
                $scheduler->handleIoWrite($socket, $task);
            }
        );
    }

    function handleClient($socket): \Generator
    {
        yield $this->waitIoRead($socket);

        $contents = '';
        while (!feof($socket)) {
            $buf = stream_socket_recvfrom($socket, self::MTU_SIZE);
            $contents .= $buf;

            if (strlen($buf) < self::MTU_SIZE) {
                break;
            }
        }

        $msg = "Received following request:\n\n$contents";
        $msgLength = strlen($msg);

        $response = <<<RESP
HTTP/1.1 200 OK\r
Content-Type: text/plain\r
Content-Length: $msgLength\r
Connection: close\r
\r
$msg
RESP;

        yield $this->waitIoWrite($socket);

        stream_socket_sendto($socket, $response);
        stream_socket_shutdown($socket, STREAM_SHUT_RDWR);
    }
}
