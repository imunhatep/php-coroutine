<?php
namespace App\Service;

class MemoryLogger
{
    function __invoke()
    {
        $mem = memory_get_usage(true);
        $pMem = memory_get_peak_usage(true);

        dump(
            sprintf(
                '[%s] Memory in use: %0.1fM, peak: %0.1fM',
                date('H:i:s'),
                ($mem / 1024 / 1024),
                ($pMem / 1024 / 1024)
            )
        );
    }
}
