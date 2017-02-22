<?php
namespace App\Service\Kernel;

use App\Entity\KernelCall;
use App\Entity\Process;
use App\Entity\ProcessInterface;
use App\Entity\ResourceList;
use App\Entity\TickProcess;
use Collection\Map;
use Collection\MapInterface;
use React\EventLoop\Timer\Timer;
use React\EventLoop\Timer\TimerInterface;
use React\EventLoop\Timer\Timers;

abstract class AbstractKernel implements KernelInterface
{
    /** @var int */
    protected $processId;

    /** @var bool */
    protected $running;

    /** @var MapInterface */
    protected $processQueue;

    /** @var Timers */
    protected $timerQueue;

    /** @var ResourceList */
    protected $ioReadQueue;

    /** @var ResourceList */
    protected $ioWriteQueue;

    function __construct()
    {
        $this->processId = 0;
        $this->running = false;

        $this->processQueue = new Map;
        $this->timerQueue = new Timers;

        $this->ioReadQueue = new ResourceList;
        $this->ioWriteQueue = new ResourceList;
    }

    function schedule(\Generator $task): int
    {
        return $this->scheduleProcess(new Process($task));
    }

    function scheduleProcess(ProcessInterface $process): int
    {
        $this->enqueueProcess(++$this->processId, $process);

        return $this->processId;
    }

    /**
     * Enqueue a callback to be invoked once after the given interval.
     *
     * The execution order of timers scheduled to execute at the same time is
     * not guaranteed.
     *
     * @param int|float $interval The number of seconds to wait before execution.
     * @param callable  $callback The callback to invoke.
     *
     * @return TimerInterface
     */
    function addTimer($interval, callable $callback): TimerInterface
    {
        $timer = new Timer($this, $interval, $callback, false);

        $this->timerQueue->add($timer);

        return $timer;
    }

    /**
     * Enqueue a callback to be invoked repeatedly after the given interval.
     *
     * The execution order of timers scheduled to execute at the same time is
     * not guaranteed.
     *
     * @param int|float $interval The number of seconds to wait before execution.
     * @param callable  $callback The callback to invoke.
     *
     * @return TimerInterface
     */
    function addPeriodicTimer($interval, callable $callback): TimerInterface
    {
        $timer = new Timer($this, $interval, $callback, true);

        $this->timerQueue->add($timer);

        return $timer;
    }

    /**
     * Cancel a pending timer.
     *
     * @param TimerInterface $timer The timer to cancel.
     */
    function cancelTimer(TimerInterface $timer)
    {
        $this->timerQueue->cancel($timer);
    }

    /**
     * Check if a given timer is active.
     *
     * @param TimerInterface $timer The timer to check.
     *
     * @return boolean True if the timer is still enqueued for execution.
     */
    function isTimerActive(TimerInterface $timer): bool
    {
        return $this->timerQueue->contains($timer);
    }

    function handleIoRead($socket, ProcessInterface $task)
    {
        $this->ioReadQueue->add($socket, $task);
    }

    function handleIoWrite($socket, ProcessInterface $task)
    {
        $this->ioWriteQueue->add($socket, $task);
    }

    function kill(int $pid)
    {
        if (!$this->processQueue->containsKey($pid)) {
            return false;
        }

        $this->processQueue->remove($pid);

        return true;
    }

    /**
     * Run the event loop until there are no more tasks to perform.
     */
    function run()
    {
        $this->running = true;

        $this->schedule($this->ioPollTask());

        while ($this->running) {
            $this->timerQueue->tick();
            $this->tick();

            //gc_collect_cycles();
        }
    }

    /**
     * Instruct a running event loop to stop.
     */
    function stop()
    {
        $this->running = false;
    }

    /**
     * Perform a single iteration of the event loop.
     */
    function tick()
    {
        /** @var Process $process */
        $process = $this->dequeueProcess();

        //dump('Tick pid: '. $process->getPid());

        $retVal = $process();
        if ($retVal and ($retVal instanceof KernelCall)) {
            $retVal($process, $this);

            return;
        }

        if (!$process->isFinished()) {
            $this->enqueueProcess($process->getPid(), $process);
        }
    }

    protected function ioPollTask(): \Generator
    {
        while (true) {
            $this->ioPoll($this->processQueue->isEmpty() ? 10000 : 0);
            yield;
        }
    }

    protected function timerTask(): \Generator
    {
        while (true) {
            $this->ioPoll($this->processQueue->isEmpty() ? 10000 : 0);
            yield;
        }
    }

    protected function ioPoll(int $usec)
    {
        $rSocks = $this->ioReadQueue
            ->filterTimedOut(function ($s) { dump('Dead read resource: #'.(int)$s); })
            ->all()
        ;

        $wSocks = $this->ioWriteQueue
            ->filterTimedOut(function ($s) { dump('Dead write resource: #'.(int)$s); })
            ->all()
        ;

        //dump(count($rSocks).' read, '.count($wSocks).' write');

        if (!$rSocks and !$wSocks) {
            return;
        }

        // dummy
        $eSocks = [];
        if (!stream_select($rSocks, $wSocks, $eSocks, 0, $usec)) {
            return;
        }

        foreach ($rSocks as $socket) {
            list(, $processes) = $this->ioReadQueue->get($socket)->get();
            $this->ioReadQueue->remove($socket);

            foreach ($processes as $process) {
                $this->scheduleProcess($process);
            }
        }

        foreach ($wSocks as $socket) {
            list(, $processes) = $this->ioWriteQueue->get($socket)->get();
            $this->ioWriteQueue->remove($socket);

            foreach ($processes as $process) {
                $this->scheduleProcess($process);
            }
        }
    }

    private function enqueueProcess(int $pid, ProcessInterface $process)
    {
        $process->setPid($pid);

        if ($this->processQueue->containsKey($pid)) {
            throw new \RuntimeException('Kernel panic: process with given id already in queue');
        }

        $this->processQueue->set($pid, $process);
    }

    private function dequeueProcess(): ProcessInterface
    {
        /** @var array $taskTuple */
        $taskTuple = $this->processQueue
            ->headOption()
            ->getOrElse([0, TickProcess::instance()])
        ;

        //$this->processQueue = $this->processQueue->tail();
        $this->processQueue->containsKey($taskTuple[0]) and $this->processQueue->remove($taskTuple[0]);

        return $taskTuple[1];
    }
}
