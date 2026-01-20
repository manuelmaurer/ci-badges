<?php

use App\Middleware\ApiKeyAuthMiddleware;
use DI\Bridge\Slim\Bridge;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Middlewares\Whoops;
use Psr\Container\ContainerInterface;
use Slim\App;

use function DI\autowire;

return [
    ApiKeyAuthMiddleware::class => function (ContainerInterface $container) {
        return new ApiKeyAuthMiddleware($container->get('apiKey'));
    },
    ClientInterface::class => function (ContainerInterface $container) {
        return new Client(['base_uri' => $container->get('shieldUrl')]);
    },
    App::class => function (ContainerInterface $container) {
        // Initialize slim application
        $app = Bridge::create($container);
        $debug = $container->has('debug') && $container->get('debug');
        if ($debug) {
            $app->add($container->get(Whoops::class));
        }
        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();
        // Register routes
        (require __DIR__ . '/routes.php')($app);
        $app->addErrorMiddleware($debug, true, $debug);
        return $app;
    },
    Whoops::class => autowire(),
];
