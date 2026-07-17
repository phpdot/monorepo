<?php

declare(strict_types=1);

namespace PHPdot\Template\Tests\Stubs;

use Psr\Container\ContainerInterface;
use RuntimeException;

final class ArrayContainer implements ContainerInterface
{
    /**
     * @param array<string, object> $entries
     */
    public function __construct(
        private array $entries = [],
    ) {}

    public function set(string $id, object $entry): void
    {
        $this->entries[$id] = $entry;
    }

    public function get(string $id): object
    {
        if (!array_key_exists($id, $this->entries)) {
            throw new RuntimeException("No entry for '{$id}'.");
        }

        return $this->entries[$id];
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->entries);
    }
}
