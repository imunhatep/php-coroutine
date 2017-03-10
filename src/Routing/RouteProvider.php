<?php
namespace App\Routing;

use App\Controller\DummyController;
use App\Controller\TriggerController;
use Aura\Router\Route;
use Aura\Router\RouterContainer;

class RouteProvider
{
    static function build(): RouterContainer
    {
        //Create the router
        $router = new RouterContainer();

        $map = $router->getMap();

        $map->get(
            'welcome',
            '/',
            [new DummyController, 'indexAction']
        );

        $map->addRoute(
            (new Route)
                ->name('trigger')
                ->path('/as-ci/trigger.json')
                ->handler([new TriggerController, 'indexAction'])
                ->allows(['GET','POST'])
        );

        return $router;
    }
}