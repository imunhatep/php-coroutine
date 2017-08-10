<?php
namespace Coroutine\Entity;

interface TaskInterface
{
    function __invoke();

    function getTitle(): string;

    function getPid(): int;

    function hasPid(): bool;

    function setPid(int $pid);

    function setSendValue($sendValue);

    function isFinished(): bool;
}
