<?php

declare(strict_types=1);

use DI\ContainerBuilder;

require __DIR__ . '/../vendor/autoload.php';

Dotenv\Dotenv::createImmutable(__DIR__ . '/../')->safeLoad();

$builder = new ContainerBuilder();
$builder->addDefinitions((require __DIR__ . '/config.php'));
$builder->addDefinitions((require __DIR__ . '/container.php'));
return $builder->build();
