<?php

declare(strict_types=1);

use App\Controller\BadgeController;
use App\Middleware\ApiKeyAuthMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    $app->setBasePath('/v1');
    $app->group('/badges', function (RouteCollectorProxy $group) {
        $group->get('/{badgeName}', [BadgeController::class, 'badgeGet'])
            ->setName('badge.get');

        $group->delete('/{badgeName}', [BadgeController::class, 'deleteBadge'])
            ->add(ApiKeyAuthMiddleware::class)
            ->setName('badge.delete');

        $group->put('/{badgeName}', [BadgeController::class, 'updateBadge'])
            ->add(ApiKeyAuthMiddleware::class)
            ->setName('badge.update');
    });

    $app->post('/coverage-reports', [BadgeController::class, 'coverageReport'])
        ->add(ApiKeyAuthMiddleware::class)
        ->setName('badge.coverage');
};
