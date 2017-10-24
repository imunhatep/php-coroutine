<?php

namespace Coroutine\Entity;

use Collection\Map;
use Collection\MapInterface;
use Coroutine\Kernel\AbstractKernel;
use PhpOption\Option;

class ResourceList
{
    /** @var MapInterface */
    private $resources;

    function __construct()
    {
        $this->resources = new Map;
    }

    function get($socket): Option
    {
        if (!is_resource($socket)) {
            throw new \InvalidArgumentException('ResourceList::add() expects a valid IO resource as first argument');
        }

        return $this->resources->get((int)$socket);
    }

    function all(): array
    {
        return $this
            ->resources
            ->foldLeft(
                [],
                function (array $memo, int $k, array $resourceTuple): array {
                    list($socket,) = $resourceTuple;
                    $memo[] = $socket;

                    return $memo;
                }
            );
    }

    function add($socket, TaskInterface $task): ResourceList
    {
        if (!is_resource($socket)) {
            throw new \InvalidArgumentException('ResourceList::add() expects a valid IO resource as first argument');
        }

        AbstractKernel::DEBUG and dump(
            sprintf(
                '[%s] Resource[%d] task pid[%d]: %s',
                date('H:i:s'),
                (int)$socket,
                $task->getPid(),
                $task->getTitle()
            )
        );

        $resourceId = (int)$socket;

        /** @var array $resourceTuple */
        $resourceTuple = $this->resources->get($resourceId)->getOrElse([$socket, []]);

        $resourceTuple[1][] = $task;

        $this->resources->set($resourceId, $resourceTuple);

        return $this;
    }

    function remove($socket): ResourceList
    {
        if (!is_resource($socket)) {
            throw new \InvalidArgumentException('ResourceList::remove() expects a valid IO resource as first argument');
        }

        $this->resources->containsKey((int)$socket) and $this->resources->remove((int)$socket);

        return $this;
    }

    function isEmpty(): bool
    {
        return $this->resources->isEmpty();
    }

    function length(): int
    {
        return $this->resources->length();
    }

    function filterDead(): ResourceList
    {
        $debug = AbstractKernel::DEBUG;

        $this->resources = $this
            ->resources
            ->filter(
                function (int $k, array $resourceTuple) use ($debug): bool {
                    list($socket, $processes) = $resourceTuple;

                    if (!empty($processes)) {
                        return true;
                    }

                    if ($endOfFile = feof($socket) and $debug) {
                        dump('Dead by eof resource: #' . (int)$socket);

                        return false;
                    }

                    $meta = stream_get_meta_data($socket);
                    if ($meta['timed_out'] and $debug) {
                        dump('Dead by timeout resource: #' . (int)$socket);

                        return false;
                    }

                    return true;
                }
            );

        return $this;
    }
}
