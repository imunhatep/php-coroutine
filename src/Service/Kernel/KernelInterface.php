<?php
namespace App\Service\Kernel;

use App\Entity\ProcessInterface;
use React\EventLoop\LoopInterface;

interface KernelInterface extends LoopInterface
{
    function schedule(\Generator $task): int;
    function scheduleProcess(ProcessInterface $process): int;
    function kill(int $pid);

    function handleIoRead($socket, ProcessInterface $task);
    function handleIoWrite($socket, ProcessInterface $task);
}
