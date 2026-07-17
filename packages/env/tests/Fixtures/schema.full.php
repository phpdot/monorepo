<?php

declare(strict_types=1);

use PHPdot\Env\Enum\AppEnv;
use PHPdot\Env\Enum\EnvType;

return [
    'APP_ENV' => ['enum' => AppEnv::class, 'default' => AppEnv::DEVELOPMENT],
    'APP_DEBUG' => ['type' => EnvType::BOOL, 'default' => false],
    'APP_PORT' => ['type' => EnvType::INT, 'default' => 8080, 'min' => 1, 'max' => 65535],
    'APP_URL' => ['type' => EnvType::STRING, 'required' => true, 'pattern' => '/^https?:\/\//'],
    'APP_KEY' => ['type' => EnvType::STRING, 'required' => true, 'not_empty' => true, 'sensitive' => true],
    'DB_HOST' => ['required' => true],
    'DB_PORT' => ['type' => EnvType::INT, 'default' => 5432],
    'ALLOWED_ORIGINS' => ['type' => EnvType::LIST, 'default' => []],
    'FEATURE_CONFIG' => ['type' => EnvType::JSON, 'default' => []],
    'LOG_LEVEL' => ['default' => 'info', 'allowed' => ['debug', 'info', 'notice', 'warning', 'error']],
];
