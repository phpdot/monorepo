<?php

declare(strict_types=1);

/**
 * A block declaring a wire shape no factory serves — the refusal DriversTest
 * proves.
 */

return [
    'providers' => [
        'bedrock-vendor' => [
            'driver' => 'bedrock',
            'apiKey' => 'key',
        ],
    ],
];
