<?php
namespace App\Entity;

interface ProcessInterface
{
    function __invoke();

    function getPid(): int;

    function setPid(int $pid);

    function setSendValue($sendValue);

    function isFinished(): bool;
}
