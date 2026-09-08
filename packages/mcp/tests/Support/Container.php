<?php

declare(strict_types=1);

namespace PHPdot\Mcp\Tests\Support;

use Psr\Container\ContainerInterface;

/**
 * The smallest PSR-11 that resolves: a map of id to factory. Enough to prove the
 * registry builds tool classes through a container and nothing more.
 */
final class Container implements ContainerInterface
{
    /** @var array<object> */
    private array $built = [];

    /**
     * @param array<string, callable(): object> $factories What the container knows how to build
     */
    public function __construct(private readonly array $factories = []) {}

    /**
     * @param string $id The service id
     *
     * @return object
     */
    public function get(string $id): object
    {
        if (!isset($this->factories[$id])) {
            throw new class ($id) extends \RuntimeException {
                public function __construct(string $id)
                {
                    parent::__construct(sprintf('No service is named [%s].', $id));
                }
            };
        }

        return $this->built[$id] ??= ($this->factories[$id])();
    }

    /**
     * @param string $id The service id
     *
     * @return bool
     */
    public function has(string $id): bool
    {
        return isset($this->factories[$id]);
    }
}
