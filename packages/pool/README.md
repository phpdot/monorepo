# phpdot/pool

Generic, coroutine-safe connection pool for Swoole. Holds objects of any type behind a
`Swoole\Coroutine\Channel`, so borrowing and releasing are lock-free at the C level. Creates
connections up to a cap, reaps idle ones, optionally heartbeats them, validates on borrow and
return, and prevents leaks and cross-coroutine sharing — created in `onWorkerStart`, closed in
`onWorkerStop`.

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Usage](#usage)
  - [Define a Connector](#define-a-connector)
  - [Create and Initialize](#create-and-initialize)
  - [Borrow and Release](#borrow-and-release)
  - [Discard](#discard)
  - [Configuration](#configuration)
  - [Idle Cleanup](#idle-cleanup)
  - [Heartbeat](#heartbeat)
  - [Validate on Borrow and Return](#validate-on-borrow-and-return)
  - [Stats](#stats)
  - [Shutdown and Draining](#shutdown-and-draining)
  - [Framework Wiring](#framework-wiring)
- [Architecture](#architecture)
- [Testing](#testing)
- [License](#license)

## Requirements

| Requirement | Constraint |
|---|---|
| PHP | `>= 8.5` |
| `ext-swoole` | `>= 6.2` |
| `phpdot/config` | `^0.3` |
| `phpdot/contracts` | `^0.3` |
| `psr/container` | `^2.0` |

## Installation

```bash
composer require phpdot/pool
```

## Usage

### Define a Connector

The pool does not know what it pools. A connector — implementing
`PHPdot\Contracts\Pool\ConnectorInterface` (shipped by `phpdot/contracts`) — tells it how to
create, health-check, and close the underlying object.

```php
use PHPdot\Contracts\Pool\ConnectorInterface;

final class RedisConnector implements ConnectorInterface
{
    public function connect(): object
    {
        $redis = new \Redis();
        $redis->connect('127.0.0.1', 6379);

        return $redis;
    }

    public function isAlive(object $connection): bool
    {
        return $connection->ping() === true; // lightweight server round-trip
    }

    public function close(object $connection): void
    {
        $connection->close();
    }
}
```

`isAlive()` should be a single cheap round-trip (e.g. `PING`, `SELECT 1`); only a server-side
check catches connections killed by idle timeouts, firewall drops, or restarts.

### Create and Initialize

The first `borrow()` initialises the pool — `minConnections` are created and the maintenance
timers start, inside the coroutine the borrow runs in. `init()` remains for explicit
pre-warming and is idempotent: a second call, explicit or self-triggered, is a no-op.

```php
use PHPdot\Pool\Pool;
use PHPdot\Pool\PoolConfig;

$pool = new Pool(new RedisConnector(), new PoolConfig(minConnections: 4, maxConnections: 20));
$redis = $pool->borrow();        // initialises, then borrows
```

### Borrow and Release

Borrow a connection, use it, then return it. On exhaustion `borrow()` waits up to
`borrowTimeout`, growing the pool on demand up to `maxConnections`.

```php
$redis = $pool->borrow();       // object, or throws BorrowTimeoutException / PoolClosedException

try {
    $redis->set('key', 'value');
} finally {
    $pool->release($redis);     // return for reuse; double release is ignored
}
```

- `borrow(): object` — throws `PHPdot\Pool\Exception\BorrowTimeoutException` when none becomes
  available within `borrowTimeout`, or `PHPdot\Pool\Exception\PoolClosedException` after `close()`.
- `release(object $connection): void` — returns the connection to the pool; releasing an unknown
  or already-released connection is silently ignored.

### Discard

Permanently close a connection that must not be reused (a broken one), freeing its slot.

```php
$pool->discard($redis);         // close + free the slot, never re-pool
```

### Configuration

`PoolConfig` is an immutable value object (also discoverable as `#[Config('pool')]`).

```php
use PHPdot\Pool\PoolConfig;

new PoolConfig(
    minConnections: 2,                  // pre-created on init; pool never shrinks below this
    maxConnections: 10,                 // hard cap per worker
    borrowTimeout: 3.0,                 // seconds to wait when exhausted
    maxIdleTime: 300.0,                 // seconds before an idle connection is reaped (0.0 = off)
    idleCheckInterval: 30.0,            // seconds between idle-cleanup runs
    heartbeatInterval: 0.0,             // seconds between heartbeats (0.0 = off)
    validateOnBorrowAfterIdle: 5.0,     // isAlive() on borrow after N idle secs; 0.0 = always; <0 = off
    validateOnReturn: true,             // isAlive() on release; discard dead instead of re-pooling
);
```

Total connections to the backing service = workers x `maxConnections` (e.g. 4 x 10 = 40).

### Idle Cleanup

When `maxIdleTime > 0.0`, a timer every `idleCheckInterval` seconds closes connections idle
longer than `maxIdleTime`, never dropping below `minConnections`. Connections in use are untouched.

### Heartbeat

When `heartbeatInterval > 0.0`, a separate timer calls `isAlive()` on idle connections and closes
dead ones, refilling toward `minConnections`. Off by default — enable it for backends that drop
idle connections aggressively.

### Validate on Borrow and Return

- **On borrow** — when `validateOnBorrowAfterIdle >= 0.0` and a popped connection has been idle at
  least that many seconds, `isAlive()` is called before hand-off; a dead one is closed and the
  borrow loop tries again. `0.0` validates every borrow; a negative value disables it.
- **On return** — when `validateOnReturn` is `true` (default), `release()` calls `isAlive()` and
  discards (rather than re-pools) dead connections, so a connection poisoned mid-use cannot be
  handed straight back out.

### Stats

`stats()` returns an immutable `PoolStats` snapshot for monitoring and health checks.

```php
$s = $pool->stats();
$s->active; $s->idle; $s->total;                     // live counts
$s->borrowCount; $s->releaseCount; $s->discardCount; // lifetime counters
$s->createCount; $s->closeCount; $s->timeoutCount; $s->waitingCount;
```

### Shutdown and Draining

- `close(): void` — full synchronous shutdown: stop timers, drain and close idle connections;
  borrowed connections close on their later release. `isClosed(): bool` reports the state.
- `suspendTimers(): void` — stop the idle/heartbeat timers **without** closing the pool, so
  in-flight `borrow()` calls still complete against live connections. Use it on `onWorkerExit`
  during a graceful drain; the OS closes pooled connections when the worker exits.

### Framework Wiring — the DI way

Applications do not touch `Pool` at all. `pooled()` binds a connection class to a named pool
as a **scoped definition**: the first resolution inside a coroutine borrows from the pool,
every later resolution in the same coroutine returns the same connection, and coroutine end
releases it — the container's own scoping does the lifecycle. Outside a coroutine (CLI, boot,
migrations) the same definition hands a dedicated, unpooled connection.

```php
use function PHPdot\Pool\pooled;

$builder->add(DatabaseConnection::class, pooled('database', DatabaseConnector::class));
$builder->add('redis.cache',             pooled('redis.cache', RedisConnector::class));
```

Each pool reads its own client's config block: the `pool` key sizes the pool, everything
else in the block hydrates the connector's configuration — when that parameter is a concrete
class (`RedisConnector(RedisConfig)`), automatically; when it is an interface
(`DatabaseConnector(ConnectionConfig)`), bind the interface and the registry resolves it
from the container. Dotted names address a multi-pool client's `pools` sub-block.

```php
// config/database.php
return [
    'host' => env('DB_HOST'),
    'pool' => ['min' => 5, 'max' => 100],
];

// config/redis.php
return [
    'pools' => [
        'cache'   => ['host' => env('REDIS_CACHE_HOST'),   'pool' => ['max' => 20]],
        'session' => ['host' => env('REDIS_SESSION_HOST'), 'pool' => ['max' => 5]],
    ],
];
```

**Sizing model:** a scoped connection is held for the whole request, so a pool's `max` must
cover that worker's concurrent requests — a database pool of 100 is that model, and a
non-hookable client like MongoDB (`'pool' => ['max' => 1]`) serialises through a single
connection. Long-lived coroutines (SSE, WebSocket streams) must not hold a scoped connection
for the stream's lifetime: resolve them through `PoolRegistry::connection()` and release
when the work, not the stream, ends.

The one lifecycle an application still owns is the worker-exit drain — stop the maintenance
timers so an exiting worker's event loop can close, never closing the pool itself:

```php
#[ServerListener]
final class DrainPools
{
    public function __construct(private readonly PoolRegistry $pools) {}

    public function __invoke(WorkerExiting $event): void
    {
        $this->pools->suspendAll();
    }
}
```

For direct, manual wiring the low-level hooks remain: construct the pool after the worker
fork, `suspendTimers()` on worker exit, `close()` on worker stop.

## Architecture

```mermaid
graph TD
    CALLER["Caller coroutine<br/><br/>borrow() → use → release()"]

    subgraph Pool
        direction TB
        CHAN["Coroutine Channel of PooledItem<br/><br/>Coroutine-safe bounded FIFO.<br/>pop() suspends only the caller,<br/>push() wakes the next waiter"]
        GROW["On-demand growth<br/><br/>reserve the slot before connect(),<br/>capped at maxConnections"]
        VALID["Validation<br/><br/>isAlive() on borrow-after-idle<br/>and on return"]
        TIMERS["Timers<br/><br/>idle cleanup + optional heartbeat"]
    end

    CONN["ConnectorInterface<br/><br/>from phpdot/contracts:<br/>connect / isAlive / close"]
    CFG["PoolConfig<br/><br/>Config('pool') — sizing, timeouts,<br/>idle cleanup, heartbeat, validation"]
    STATS["PoolStats<br/><br/>Immutable monitoring snapshot"]

    CALLER --> Pool
    CFG --> Pool
    Pool --> CONN
    Pool --> STATS
```

`Pool` is built on `Swoole\Coroutine\Channel`, a coroutine-safe bounded FIFO: `pop()` suspends
only the calling coroutine (never the worker process), `push()` wakes the next waiter, and the
lock is at the C level. Growth reserves the slot (`currentCount++`) before the yielding
`connect()`, so concurrent coroutines cannot overshoot `maxConnections`. The connection type is
supplied entirely through `ConnectorInterface`, which lives in `phpdot/contracts` — this package
depends on the contract, never on a concrete driver.

## Testing

The package is standalone-testable:

```bash
composer install
composer test        # PHPUnit
composer analyse     # PHPStan, level max + strict rules
composer cs-check    # PHP-CS-Fixer
composer check       # all three
```

## License

MIT.

**This repository is a read-only mirror**, generated by CI from
[phpdot/monorepo](https://github.com/phpdot/monorepo). [Pull requests](https://github.com/phpdot/monorepo/pulls)
and [issues](https://github.com/phpdot/monorepo/issues) belong in the monorepo.
