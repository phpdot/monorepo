<?php

declare(strict_types=1);

/**
 * The MCP boundary failed outside the protocol's own error handling.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mcp\Exception;

use Throwable;

final class GatewayException extends McpException
{
    /**
     * Translate any SDK failure into the package tree.
     *
     * @param Throwable $failure The caught failure
     *
     * @return self
     */
    public static function from(Throwable $failure): self
    {
        return new self(sprintf('The MCP request failed: %s', $failure->getMessage()), 0, $failure);
    }

    /**
     * A tool was invoked through the SDK's own dispatch instead of the reference
     * handler — the seam has moved.
     *
     * The closures registered with the builder exist to satisfy its signature and
     * are never meant to run; if one does, saying so loudly is the only honest
     * failure.
     *
     * @param string $name The tool that was invoked
     *
     * @return self
     */
    public static function handlerBypassed(string $name): self
    {
        return new self(sprintf(
            'Tool [%s] was invoked through the SDK instead of the registry — the reference handler is not wired.',
            $name,
        ));
    }
}
