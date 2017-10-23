<?php
namespace Coroutine\Entity;

use Collection\Map;
use Collection\MapInterface;
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

        $resourceId = (int)$socket;

        /** @var array $resourceTuple */
        $resourceTuple = $this->resources->containsKey($resourceId)
            ? $this->resources->get($resourceId)->get()
            : [$socket, []];

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

    function filterTimedOut(callable $debug = null): ResourceList
    {
        $this
            ->resources
            ->filterNot(
                function (int $k, array $resourceTuple) use ($debug): bool {
                    list($socket, $processes) = $resourceTuple;

                    $meta = stream_get_meta_data($socket);
                    if ($meta['timed_out'] and $debug) {
                        $debug($socket, $processes);
                    }

                    if($endOfFile = feof($socket)){
                        $debug($socket, $processes);
                    }

                    return $meta['timed_out'] or $endOfFile;
                }
            );

        return $this;
    }
}
