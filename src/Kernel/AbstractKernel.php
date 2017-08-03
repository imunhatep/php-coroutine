<?php
namespace Coroutine\Kernel;

use Coroutine\Entity\KernelCall;
use Coroutine\Entity\Task;
use Coroutine\Entity\TaskInterface;
use Coroutine\Entity\ResourceList;
use Coroutine\Entity\Tick;
use Collection\Map;
use Collection\MapInterface;
use React\EventLoop\Timer\Timer;
use React\EventLoop\Timer\TimerInterface;
use React\EventLoop\Timer\Timers;

abstract class AbstractKernel implements KernelInterface
{
    /** @var int */
    protected $taskNextId;

    /** @var bool */
    protected $running;

    /** @var MapInterface */
    protected $taskQueue;

    /** @var Timers */
    protected $timerQueue;

    /** @var ResourceList */
    protected $ioReadQueue;

    /** @var ResourceList */
    protected $ioWriteQueue;

    function __construct()
    {
        $this->taskNextId = 0;
        $this->running = false;

        $this->taskQueue = new Map;
        $this->timerQueue = new Timers;

        $this->ioReadQueue = new ResourceList;
        $this->ioWriteQueue = new ResourceList;
    }

    function schedule(\Generator $task): int
    {
        return $this->scheduleTask(new Task($task));
    }

    function scheduleTask(TaskInterface $process): int
    {
        $this->enqueueTask(++$this->taskNextId, $process);

        return $this->taskNextId;
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

    function handleIoRead($socket, TaskInterface $task)
    {
        $this->ioReadQueue->add($socket, $task);
    }

    function handleIoWrite($socket, TaskInterface $task)
    {
        $this->ioWriteQueue->add($socket, $task);
    }

    function kill(int $pid): Task
    {
        if (!$this->taskQueue->containsKey($pid)) {
            throw new \InvalidArgumentException('Invalid process id');
        }

        return $this->taskQueue->remove($pid);
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

            // sometimes.. segmentation fault on php 7.1
            gc_collect_cycles();
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
        /** @var Task $task */
        $task = $this->dequeueTask();

        //dump('Tick pid: '. $process->getPid());

        $retVal = $task();
        if ($retVal instanceof KernelCall) {
            try {
                $retVal($task, $this);
            }
            catch (\Exception $e) {
                $task->setException($e);
                $this->scheduleTask($task);
            }

            return;
        }

        if (!$task->isFinished()) {
            $this->enqueueTask($task->getPid(), $task);
        }
    }

    protected function ioPollTask(): \Generator
    {
        while (true) {
            $this->ioPoll($this->taskQueue->isEmpty() ? 10000 : 0);
            yield;
        }
    }

    protected function timerTask(): \Generator
    {
        while (true) {
            $this->ioPoll($this->taskQueue->isEmpty() ? 10000 : 0);
            yield;
        }
    }

    protected function ioPoll(int $usec)
    {
        $rSocks = $this->ioReadQueue
            ->filterTimedOut(function ($s) { dump('Dead read resource: #' . (int)$s); })
            ->all();

        $wSocks = $this->ioWriteQueue
            ->filterTimedOut(function ($s) { dump('Dead write resource: #' . (int)$s); })
            ->all();

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
                $this->scheduleTask($process);
            }
        }

        foreach ($wSocks as $socket) {
            list(, $processes) = $this->ioWriteQueue->get($socket)->get();
            $this->ioWriteQueue->remove($socket);

            foreach ($processes as $process) {
                $this->scheduleTask($process);
            }
        }
    }

    private function enqueueTask(int $pid, TaskInterface $process)
    {
        $process->setPid($pid);

        if ($this->taskQueue->containsKey($pid)) {
            throw new \RuntimeException('Kernel panic: task with given id already in queue');
        }

        $this->taskQueue->set($pid, $process);
    }

    private function dequeueTask(): Task
    {
        /** @var array $taskTuple */
        $taskTuple = $this->taskQueue->headOption()->getOrElse([0, Tick::instance()]);
        $this->taskQueue->containsKey($taskTuple[0]) and $this->taskQueue->remove($taskTuple[0]);

        return $taskTuple[1];
    }
}
