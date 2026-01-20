<?php

use Slim\App;

(require __DIR__ . '/../config/bootstrap.php')
    ->get(App::class)
    ->run();
