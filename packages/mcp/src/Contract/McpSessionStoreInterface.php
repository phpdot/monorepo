<?php

declare(strict_types=1);

/**
 * A PSR-16 store dedicated to MCP sessions — the seam a cluster binds.
 *
 * Deliberately its OWN type and not plain `CacheInterface`: the host's shared
 * cache satisfies PSR-16 too, and binding it would land sessions in a store
 * whose `clear()` takes every live MCP session with it. An empty extension is
 * how the type system says "this store belongs to the endpoint" — a cluster
 * implements it over its own pooled connection (a driver over RedisConnection
 * belongs to phpdot/cache) and binds it; a single node binds nothing and gets
 * the package's FileDriver default over the configured path.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mcp\Contract;

use Psr\SimpleCache\CacheInterface;

interface McpSessionStoreInterface extends CacheInterface {}
