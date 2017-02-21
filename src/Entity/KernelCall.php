<?php
namespace App\Entity;

use App\Service\Kernel\KernelInterface;

class KernelCall
{
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
