<?php

declare(strict_types=1);

/**
 * The `ai` configuration block exactly as a host writes it — plain arrays, no
 * env helper — consumed through a real Configuration built over this directory.
 *
 * Three providers, two wire shapes: `groq` is the point — a second provider over
 * the OpenAI shape, distinguishable from `openai` by name alone.
 */

return [
    'default' => 'anthropic',

    'timeout' => 45,
    'maxTokens' => 1024,

    'providers' => [
        'anthropic' => [
            'driver'  => 'anthropic',
            'label'   => 'Anthropic',
            'baseUrl' => 'https://api.anthropic.test/v1',
            'apiKey'  => 'test-anthropic-key',
            'version' => '2023-06-01',
            'models'  => [
                'claude-test-1' => 'Claude Test 1',
                'claude-test-2' => 'Claude Test 2',
            ],
        ],

        'openai' => [
            'driver'  => 'openai',
            'label'   => 'OpenAI',
            'baseUrl' => 'https://api.openai.test/v1',
            'apiKey'  => 'test-openai-key',
            'models'  => [
                'gpt-test' => 'GPT Test',
            ],
        ],

        'groq' => [
            'driver'  => 'openai',
            'label'   => 'Groq',
            'baseUrl' => 'https://api.groq.test/openai/v1',
            'apiKey'  => 'test-groq-key',
            'models'  => [
                'llama-test' => 'Llama Test',
            ],
        ],
    ],
];
