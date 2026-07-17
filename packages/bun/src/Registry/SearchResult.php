<?php

declare(strict_types=1);

/**
 * A single npm registry search hit, projected from the registry's `objects[].package` shape.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Bun\Registry;

final readonly class SearchResult
{
    /**
     * Hold one npm search result: name, version, description, and score.
     *
     * @param string $name
     * @param string $version
     * @param string $description
     * @param float $score
     */
    public function __construct(
        public string $name,
        public string $version,
        public string $description,
        public float $score,
    ) {}
}
