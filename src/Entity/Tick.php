<?php

namespace Coroutine\Entity;

class Tick extends Task
{
    static protected $instance;

    static function instance()
    {
        if (!static::$instance) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    function __construct(int $usecTtl = 10000)
    {
        $tick = function() use ($usecTtl): \Generator {
            while (true) {
                usleep($usecTtl);
                yield;
            }
        };

        parent::__construct($tick(), 'tik-tak');
        $this->setPid(0);
    }
}
