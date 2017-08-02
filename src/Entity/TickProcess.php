<?php

namespace App\Entity;

class TickProcess extends Process
{
    static protected $instance;

    static function instance()
    {
        if (!static::$instance) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    function __construct()
    {
        $tick = function(): \Generator {
            while (true) {
                usleep(1000);
                yield;
            }
        };

        parent::__construct($tick());
        $this->setPid(0);
    }
}
