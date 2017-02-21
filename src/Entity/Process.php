<?php
namespace App\Entity;

class Process implements ProcessInterface
{
    protected $pid;
    protected $coroutine;
    protected $sendValue;
    protected $beforeFirstYield;

    function __construct(\Generator $coroutine)
    {
        $this->coroutine = $coroutine;
        $this->beforeFirstYield = true;
    }

    function __invoke()
    {
        if ($this->beforeFirstYield) {
            $this->beforeFirstYield = false;

            return $this->coroutine->current();
        } else {
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
}
