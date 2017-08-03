<?php
namespace App\Kernel;

use App\Entity\TaskInterface;
use React\EventLoop\LoopInterface;

interface KernelInterface extends LoopInterface
{
    function schedule(\Generator $task): int;
    function scheduleTask(TaskInterface $process): int;
    function kill(int $pid);

    function handleIoRead($socket, TaskInterface $task);
    function handleIoWrite($socket, TaskInterface $task);
}
