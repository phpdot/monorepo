<?php

declare(strict_types=1);

/**
 * The seed-backed memory storage refuses mutations: an in-memory write would
 * be worker-local and lost on reload — a mirage this package refuses to sell
 * (the Casbin-memory CLI lesson). Real mutations arrive with the SQL
 * repositories at the login milestone; until then, grants are seed wiring.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Exception;

final class MemoryStorageException extends IamException
{
    /**
     * A refused mutation.
     *
     * @param string $operation What was attempted
     *
     * @return self
     */
    public static function for(string $operation): self
    {
        return new self(sprintf('Memory iam storage is seed-defined and read-only: %s requires the SQL repositories (login milestone).', $operation));
    }
}
