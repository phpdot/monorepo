<?php

declare(strict_types=1);

namespace PHPdot\Mcp\Tests\Support\Scan\Duplicate;

use PHPdot\Mcp\Tool\AsTool;

/**
 * The first claimant of a contested tool name.
 */
final class FirstTools
{
    #[AsTool(name: 'clashing_tool', permission: 'a.view', description: 'First.')]
    public function first(): array
    {
        return ['who' => 'first'];
    }
}
