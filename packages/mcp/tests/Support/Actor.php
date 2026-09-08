<?php

declare(strict_types=1);

namespace PHPdot\Mcp\Tests\Support;

use PHPdot\Mcp\Contract\ToolActorInterface;

/**
 * An actor over a fixed set of permission keys — the whole identity model the
 * package is allowed to know, in test form.
 */
final class Actor implements ToolActorInterface
{
    /**
     * @param array<string> $permissions What this actor holds
     */
    public function __construct(private readonly array $permissions = []) {}

    /**
     * @param string $permission The key a tool declared
     *
     * @return bool
     */
    public function can(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }
}
