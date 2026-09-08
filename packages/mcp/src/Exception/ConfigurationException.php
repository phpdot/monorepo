<?php

declare(strict_types=1);

/**
 * The package cannot run as configured — a server identity the host never stated, a
 * session path that does not exist, or a request that reached the endpoint without
 * an actor behind it.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mcp\Exception;

final class ConfigurationException extends McpException
{
    /**
     * The missing piece.
     *
     * @param string $what What is absent or empty
     *
     * @return self
     */
    public static function missing(string $what): self
    {
        return new self(sprintf('The MCP configuration is incomplete: %s.', $what));
    }

    /**
     * A request reached the endpoint with no actor resolvable behind it.
     *
     * The endpoint refuses rather than serve an anonymous caller: the tool list is
     * the grant, and a grant needs someone to hold it.
     *
     * @return self
     */
    public static function noActor(): self
    {
        return new self('The request reached the MCP endpoint with no actor behind it.');
    }
}
