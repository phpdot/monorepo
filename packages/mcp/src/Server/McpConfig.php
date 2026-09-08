<?php

declare(strict_types=1);

/**
 * The MCP server's identity and paths, from the host's configuration.
 *
 * The server identity is configuration and never a constant: `name` is what
 * clients see in `initialize`, and a version stated here travels with the host's
 * releases instead of drifting behind a hard-coded number that outlived its
 * truth.
 *
 * The session path is where the DEFAULT store lives: sessions must survive a
 * dispatch to a sibling Swoole worker, and the package builds that store over
 * this directory when the host binds no {@see McpSessionStoreInterface} of its
 * own. A cluster binds a dedicated store instead (over its pooled connection —
 * a driver over RedisConnection belongs to phpdot/cache) and then needs no path.
 *
 * Empty discovery directories are legal: a host that mounts the endpoint before
 * writing tools is told the server has none, which is the truthful answer.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mcp\Server;

use PHPdot\Container\Attribute\Config;
use PHPdot\Container\Attribute\Singleton;
use PHPdot\Mcp\Exception\ConfigurationException;

#[Config('mcp')]
#[Singleton]
final readonly class McpConfig
{
    /**
     * @param string $name The server name clients see in initialize
     * @param string $version The server version clients see in initialize
     * @param array<string> $discoveryDirs Absolute directories scanned for tools
     * @param string $sessionPath Writable directory for the default session store
     * @param int $sessionTtl Seconds a session survives without use
     */
    public function __construct(
        public string $name = '',
        public string $version = '',
        public array $discoveryDirs = [],
        public string $sessionPath = '',
        public int $sessionTtl = 3600,
    ) {
        if (trim($this->name) === '') {
            throw ConfigurationException::missing('the server name (mcp.name)');
        }

        if (trim($this->version) === '') {
            throw ConfigurationException::missing('the server version (mcp.version)');
        }

        if ($this->sessionTtl < 1) {
            throw ConfigurationException::missing('a session ttl of at least one second (mcp.sessionTtl)');
        }
    }
}
