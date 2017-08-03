<?php
namespace App\Entity;

use App\Kernel\KernelInterface;

class KernelCall
{
    static function callback(\Generator $coroutine): KernelCall
    {
        return new static(
            function (TaskInterface $task, KernelInterface $scheduler) use ($coroutine) {
                $task->setSendValue($scheduler->schedule($coroutine));
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
