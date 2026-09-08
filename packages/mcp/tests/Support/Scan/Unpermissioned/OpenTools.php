<?php

declare(strict_types=1);

namespace PHPdot\Mcp\Tests\Support\Scan\Unpermissioned;

use PHPdot\Mcp\Tool\AsTool;

/**
 * A tool that declares no permission — reachable and ungoverned, which discovery
 * must treat as a boot failure.
 */
final class OpenTools
{
    #[AsTool(name: 'open_tool', permission: '', description: 'Ungoverned.')]
    public function open(): array
    {
        return ['open' => true];
    }
}
