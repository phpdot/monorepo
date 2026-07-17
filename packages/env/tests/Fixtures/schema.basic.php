<?php

declare(strict_types=1);

use PHPdot\Env\Enum\EnvType;

return [
    'APP_NAME' => ['default' => 'DefaultApp'],
    'APP_PORT' => ['type' => EnvType::INT, 'default' => 3000, 'min' => 1, 'max' => 65535],
    'APP_DEBUG' => ['type' => EnvType::BOOL, 'default' => false],
    'RATE_LIMIT' => ['type' => EnvType::FLOAT, 'default' => 1.0, 'min' => 0.0],
    'DB_HOST' => ['required' => true],
];
