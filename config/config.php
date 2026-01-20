<?php

return [
    'apiKey' => $_ENV['API_KEY'] ?? null,
    'debug' => $_ENV['DEBUG'] ?? '0',
    'shieldUrl' => $_ENV['SHIELD_URL'] ?? 'https://img.shields.io',
];
