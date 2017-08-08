<?php
namespace Coroutine\Service;

use Coroutine\Entity\Task;
use Coroutine\Kernel\AbstractKernel;

class KernelLoop extends AbstractKernel
{
    /**
     * Register a listener to be notified when a stream is ready to read.
     *
     * @param resource $stream   The PHP stream resource to check.
     * @param callable $listener Invoked when the stream is ready.
     */
    public function addReadStream($stream, callable $listener)
    {
        $this->handleIoRead($stream, new Task($this->callableToGenerator($listener)));
    }

    /**
     * Register a listener to be notified when a stream is ready to write.
     *
     * @param resource $stream   The PHP stream resource to check.
     * @param callable $listener Invoked when the stream is ready.
     */
    public function addWriteStream($stream, callable $listener)
    {
        $this->handleIoWrite($stream, new Task($this->callableToGenerator($listener)));
    }

    /**
     * Remove the read event listener for the given stream.
     *
     * @param resource $stream The PHP stream resource.
     */
    public function removeReadStream($stream)
    {
        $this->ioReadQueue->remove($stream);
    }

    /**
     * Remove the write event listener for the given stream.
     *
     * @param resource $stream The PHP stream resource.
     */
    public function removeWriteStream($stream)
    {
        $this->ioWriteQueue->remove($stream);
    }

    /**
     * Remove all listeners for the given stream.
     *
     * @param resource $stream The PHP stream resource.
     */
    public function removeStream($stream)
    {
        $this->removeReadStream($stream);
        $this->removeWriteStream($stream);
    }

    /**
     * Schedule a callback to be invoked on the next tick of the event loop.
     *
     * Callbacks are guaranteed to be executed in the order they are enqueued,
     * before any timer or stream events.
     *
     * @param callable $listener The callback to invoke.
     */
    public function nextTick(callable $listener)
    {
        $this->schedule($this->callableToGenerator($listener));
    }

    /**
     * Schedule a callback to be invoked on a future tick of the event loop.
     *
     * Callbacks are guaranteed to be executed in the order they are enqueued.
     *
     * @param callable $listener The callback to invoke.
     */
    public function futureTick(callable $listener)
    {
        $this->schedule($this->callableToGenerator($listener));
    }

    protected function callableToGenerator(callable $task): \Generator
    {
        yield $task();
    }
}
