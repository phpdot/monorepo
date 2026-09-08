<?php

declare(strict_types=1);

namespace PHPdot\Mcp\Tests\Support\Scan\Clean;

use PHPdot\Mcp\Tool\AsTool;
use PHPdot\Mcp\Tool\ToolArguments;

/**
 * A clean tool layer: two governed tools and one that answers badly, over a real
 * service, with a schema beside each method that wants one.
 */
final class CatalogTools
{
    public function __construct(private readonly Catalog $catalog) {}

    #[AsTool(
        name: 'catalog_search',
        permission: 'catalog.view',
        description: 'Search the catalog by name.',
        readOnly: true,
        idempotent: true,
    )]
    public function search(ToolArguments $arguments): array
    {
        return $this->catalog->search($arguments->string('query'));
    }

    /** @return array<string, mixed> */
    public static function searchSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string'],
            ],
            'required' => ['query'],
        ];
    }

    #[AsTool(
        name: 'catalog_purge',
        permission: 'catalog.purge',
        description: 'Purge stale catalog rows.',
        destructive: true,
    )]
    public function purge(): array
    {
        return $this->catalog->purge();
    }

    #[AsTool(
        name: 'catalog_broken',
        permission: 'catalog.view',
        description: 'A tool that answers with a scalar, which nothing downstream may use.',
    )]
    public function broken(): string
    {
        return 'not an array';
    }
}
