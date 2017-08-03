<?php

namespace App\Entity;

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

    function __construct(int $ttl = 100)
    {
        $tick = function() use ($ttl): \Generator {
            while (true) {
                usleep($ttl);
                yield;
            }
        };

        parent::__construct($tick());
        $this->setPid(0);
    }
}
