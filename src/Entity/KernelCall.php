<?php
namespace App\Entity;

use App\Service\Kernel\KernelInterface;

class KernelCall
{
    static function callback(\Generator $coroutine): KernelCall
    {
        return new static(
            function (ProcessInterface $task, KernelInterface $scheduler) use ($coroutine) {
                $task->setSendValue($scheduler->schedule($coroutine));
                $scheduler->scheduleProcess($task);
            }
        );
    }

    protected $callback;

    function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    function __invoke(ProcessInterface $process, KernelInterface $kernel)
    {
        $callback = $this->callback; // Can't call it directly in PHP :/
        return $callback($process, $kernel);
    }
}
