<?php
namespace App\Entity;

class Task implements TaskInterface
{
    /** @var int */
    protected $pid;

    /** @var \Generator */
    protected $coroutine;

    /** @var mixed */
    protected $sendValue;

    /** @var bool */
    protected $beforeFirstYield;

    /** @var \Exception */
    protected $exception;

    function __construct(\Generator $coroutine)
    {
        $this->coroutine = $this->stacked($coroutine);
        $this->beforeFirstYield = true;
    }

    function __invoke()
    {
        if ($this->beforeFirstYield) {
            $this->beforeFirstYield = false;

            return $this->coroutine->current();
        }
        else if ($this->exception) {
            $result = $this->coroutine->throw($this->exception);
            $this->exception = null;

            return $result;
        }
        else {
            $value = $this->sendValue;
            $this->sendValue = null;

            return $this->coroutine->send($value);
        }
    }

    function getPid(): int
    {
        return $this->pid;
    }

    function setPid(int $pid)
    {
        $this->pid = $pid;
    }

    function setSendValue($sendValue)
    {
        $this->sendValue = $sendValue;
    }

    function isFinished(): bool
    {
        return !$this->coroutine->valid();
    }

    function setException(\Throwable $exception)
    {
        $this->exception = $exception;
    }

    private function stacked(\Generator $gen)
    {
        $stack = new \SplStack;
        $exception = null;

        for (; ;) {
            try {
                if ($exception) {
                    $gen->throw($exception);
                    $exception = null;
                    continue;
                }

                $value = $gen->current();

                if ($value instanceof \Generator) {
                    $stack->push($gen);
                    $gen = $value;
                    continue;
                }

                $isReturnValue = $value instanceof TaskResult;
                if (!$gen->valid() or $isReturnValue) {
                    if ($stack->isEmpty()) {
                        return;
                    }

                    $gen = $stack->pop();
                    $gen->send($isReturnValue ? $value->getValue() : null);
                    continue;
                }

                try {
                    $sendValue = (yield $gen->key() => $value);
                }
                catch (\Exception $e) {
                    $gen->throw($e);
                    continue;
                }

                $gen->send($sendValue);
            }
            catch (\Exception $e) {
                if ($stack->isEmpty()) {
                    throw $e;
                }

                $gen = $stack->pop();
                $exception = $e;
            }
        }
    }
}
