#!/usr/bin/env php
<?php
set_time_limit(0);

(@include_once __DIR__.'/../vendor/autoload.php') || @include_once __DIR__.'/../../../autoload.php';

use App\Kernel\KernelInterface;
use App\Service\KernelLoop;

function echoTimes($msg, $max) {
    for ($i = 1; $i <= $max; ++$i) {
        echo "$msg iteration $i\n";
        yield;
    }
}

function task() {
    yield echoTimes('foo', 10); // print foo ten times
    echo "---\n";
    yield echoTimes('bar', 5); // print bar five times
    yield; // force it to be a coroutine
}

$scheduler = new KernelLoop;
$scheduler->schedule(task());
$scheduler->run();


