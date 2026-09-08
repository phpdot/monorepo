<?php

declare(strict_types=1);

namespace PHPdot\Mcp\Tests\Support\Scan\Clean;

/**
 * The service a tool layer holds — the thing that must stay oblivious to being
 * called by a model.
 */
final class Catalog
{
    public function __construct(public readonly string $marker = 'catalog') {}

    /**
     * @return array<string, mixed>
     */
    public function search(string $query): array
    {
        return ['query' => $query, 'via' => $this->marker];
    }

    /**
     * @return array<string, mixed>
     */
    public function purge(): array
    {
        return ['purged' => true, 'via' => $this->marker];
    }
}
