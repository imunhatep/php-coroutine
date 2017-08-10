<?php
namespace Coroutine\Entity;

use Coroutine\Kernel\KernelInterface;

class KernelCall
{
    static function callback(\Generator $coroutine, string $title): KernelCall
    {
        return new static(
            function (TaskInterface $task, KernelInterface $scheduler) use ($coroutine, $title) {
                $task->setSendValue($scheduler->schedule($coroutine, $title));
                $scheduler->scheduleTask($task);
            }
        );
    }

    protected $callback;

    function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    function __invoke(TaskInterface $process, KernelInterface $kernel)
    {
        $callback = $this->callback; // Can't call it directly in PHP :/
        return $callback($process, $kernel);
    }
}
