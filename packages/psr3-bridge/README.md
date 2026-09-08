# phpdot/psr3-bridge

A PSR-3 / Monolog writer for the PHPdot observability engine.

`psr-bridge` speaks PSR-3 in **both directions**. `Psr3Writer` is a **backend** for [phpdot/logs](https://github.com/phpdot/logs): it implements the engine's `WriterInterface` and forwards every record — a log line or a finished span — to an injected PSR-3 logger (Monolog, or any other). `TracerLogger` is the inbound peer: it implements `Psr\Log\LoggerInterface` over the tracer, so anything that type-hints a PSR-3 logger logs through the engine, trace-correlated.

`Psr3Writer` is a **peer** of [phpdot/tracelog](https://github.com/phpdot/tracelog) (the file backend). An application binds exactly one of them as its `WriterInterface`; the packages that log never know which. It depends on [contracts](https://github.com/phpdot/contracts), [logs](https://github.com/phpdot/logs), and `psr/log` — **never on tracelog** — so a Monolog-only app installs `{contracts, logs, psr3-bridge}` and never pulls the file writer or its OpenSSL/encryption code.

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Usage](#usage)
  - [Quick start](#quick-start)
  - [How records map](#how-records-map)
  - [Trace correlation](#trace-correlation)
  - [Crash-safety & no sampling](#crash-safety--no-sampling)
  - [Any PSR-3 logger](#any-psr-3-logger)
  - [TracerLogger: the inbound bridge](#tracerlogger-the-inbound-bridge)
- [Architecture](#architecture)
- [Testing](#testing)
- [License](#license)

## Requirements

| Requirement | Constraint |
|---|---|
| PHP | `>= 8.5` |
| `phpdot/contracts` | `^0.3` |
| `phpdot/logs` | `^0.3` — `TracerLogger` renders `exception` context through the engine's `_e()` |
| `psr/log` | `^3.0` |

`phpdot/container` is `require-dev` only (and a `suggest` entry) — the `#[Singleton]` attributes in
`src` stay inert until a phpdot application reflects them, so standalone consumers don't need it
installed.

## Installation

```bash
composer require phpdot/psr3-bridge monolog/monolog
```

(`monolog/monolog` is a suggestion, not a hard dependency — any PSR-3 logger works.)

## Usage

### Quick start

Bind `Psr3Writer` as the engine's `WriterInterface`, pointed at any PSR-3 logger:

```php
use PHPdot\Contracts\Logs\WriterInterface;
use PHPdot\Psr3Bridge\Psr3Writer;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$container->set(WriterInterface::class, static fn () =>
    new Psr3Writer(
        (new Logger('app'))->pushHandler(new StreamHandler('php://stdout')),
    ),
);
```

Your packages keep logging against `TracerInterface` — only this binding changes. From here, everything the engine emits flows through your Monolog handler stack (Slack, syslog, Elasticsearch, rotating files, …).

### How records map

The engine hands the writer a flat `array<string, mixed>`. `Psr3Writer` translates it to a PSR-3 `log($level, $message, $context)` call:

#### Log records

Forwarded at their own level, with the trace correlation attached to the PSR-3 context:

```php
$tracer->channel('http')->warning('slow upstream', ['ms' => 820]);
```
```
// reaches the PSR-3 logger as:
$logger->log('warning', 'slow upstream', [
    'ms'       => 820,
    'channel'  => 'http',
    'trace_id' => '019f15…',
    'span_id'  => 'c17527…',
]);
```

The engine's level token is validated against the eight PSR-3 levels; an unknown token falls back to `info`.

#### Span records

A finished span becomes **one** line — `span <name>` — at `info`, or `error` when the span's status is `error`, with its metadata in the context:

```
$logger->log('info', 'span db.query', [
    'channel' => 'db', 'trace_id' => '019f15…', 'span_id' => 'a1b2c3…',
    'parent_span_id' => 'c17527…', 'kind' => 'client', 'duration_ms' => 4.2,
    'status' => 'ok', 'status_message' => '', 'attributes' => ['db.rows' => 5], 'events' => [],
]);
```

So even on a backend that has no concept of spans, the full span tree is preserved as correlated log lines you can group by `trace_id`.

### Trace correlation

`channel`, `trace_id`, and `span_id` are always added to the PSR-3 context, so every line a downstream handler sees carries the trace — group by `trace_id` in your log aggregator to reassemble a request.

### Crash-safety & no sampling

- **Never throws:** `write()` wraps the forward in a single `try/catch`. A misbehaving logger (a dead socket, a full disk) is swallowed so logging can never bring down the caller or the coroutine-end span flush. This is the *only* `try/catch` — it is crash-safety, never a drop decision.
- **No sampling:** every record received is forwarded. Sampling/retention is left to your Monolog handlers, not decided here.

### Any PSR-3 logger

Nothing here is Monolog-specific — `Psr3Writer` takes a `Psr\Log\LoggerInterface`. Bind Laminas, Symfony's logger, a test spy, or your own:

```php
new Psr3Writer($anyPsr3Logger);
```

### TracerLogger: the inbound bridge

`TracerLogger` implements `Psr\Log\LoggerInterface` over the tracer, so the ecosystem's PSR-3 consumers — phpdot's event, database, rabbitmq, and error-handler packages, or any third-party library — log through the engine with trace and span correlation. Applications bind it **explicitly** (it deliberately carries no `#[Binds]`, so installing this package never silently redefines an application's logger):

```php
use Psr\Log\LoggerInterface;
use PHPdot\Contracts\Logs\TracerInterface;
use PHPdot\Psr3Bridge\TracerLogger;

$builder->add(LoggerInterface::class, static fn ($c) => new TracerLogger(
    $c->get(TracerInterface::class),
))->singleton();
```

Behavior at the boundary:

- Every PSR-3 level maps 1:1 onto the tracer's — `notice`, `critical`, `alert`, and `emergency` survive intact; unknown tokens fall back to `info`.
- A PSR-3 `exception` context value is converted to the engine's canonical `_e()` shape (`class`, `message`, `code`, `file`, `line`), so every exception line carries the same structure regardless of origin.
- `secure()` is unreachable from the PSR-3 side by design — encryption is a tracer-API capability; sensitive logging stays on the tracer.

**The no-loop rule:** never hand a `TracerLogger` to a `Psr3Writer` that the same tracer exports to — that wiring recurses (the writer forwards to the logger, which feeds the tracer, which exports to the writer). Construct the outbound `Psr3Writer` with a hand-built Monolog stack instead. A re-entrancy guard inside `TracerLogger` drops calls that arrive from within a write, turning a would-be unbounded recursion into one dropped line — but the guard is a backstop, not a license to wire the loop.

## Architecture

```mermaid
graph TD
    REC["Record from phpdot/logs<br/><br/>arrives via WriterInterface"]
    W["Psr3Writer<br/><br/>the WriterInterface backend:<br/>maps each record to a PSR-3 call,<br/>keeps trace_id / span_id in context"]
    PSR["Any PSR-3 logger<br/><br/>Monolog, Laminas, Analog, …"]
    REC --> W --> PSR

    IN["PSR-3 consumers<br/><br/>phpdot event / database / rabbitmq /<br/>error-handler, third-party libraries"]
    TL["TracerLogger<br/><br/>the LoggerInterface facade:<br/>same level, _e() exceptions,<br/>re-entrancy-guarded"]
    TR["phpdot/logs tracer"]
    IN --> TL --> TR
```

## Testing

The package is standalone-testable:

```bash
composer install
composer test        # PHPUnit
composer analyse     # PHPStan, level max + strict rules
composer cs-check    # PHP-CS-Fixer (@PER-CS2.0)
composer check       # all three
```

## License

MIT

**This repository is a read-only mirror**, generated by CI from
[phpdot/monorepo](https://github.com/phpdot/monorepo). [Pull requests](https://github.com/phpdot/monorepo/pulls)
and [issues](https://github.com/phpdot/monorepo/issues) belong in the monorepo.
