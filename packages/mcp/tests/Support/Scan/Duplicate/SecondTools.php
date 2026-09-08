<?php

declare(strict_types=1);

namespace PHPdot\Mcp\Tests\Support\Scan\Duplicate;

use PHPdot\Mcp\Tool\AsTool;

/**
 * The second claimant of the same tool name — the discovery must refuse the pair.
 */
final class SecondTools
{
    #[AsTool(name: 'clashing_tool', permission: 'a.view', description: 'Second.')]
    public function second(): array
    {
        return ['who' => 'second'];
    }
}
