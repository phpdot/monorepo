# phpdot/monorepo

PHPdot framework monorepo — every phpdot package, developed and tested together, published as
read-only per-package mirrors.

## Packages

The 37 packages in this monorepo, each published as a read-only mirror at `github.com/phpdot/<name>`:

| Package | Description |
|---|---|
| [`phpdot/attribute`](packages/attribute) | PHP 8 attribute scanning, caching, and discovery. Standalone. |
| [`phpdot/bun`](packages/bun) | A PHP wrapper around the Bun binary (oven-sh/bun, MIT licensed): manages a hidden Bun runtime and exposes its CLI as console commands. |
| [`phpdot/cache`](packages/cache) | PSR-16 cache with pluggable drivers for modern PHP. |
| [`phpdot/config`](packages/config) | Typed, cached, dot-notation configuration for modern PHP. |
| [`phpdot/console`](packages/console) | Attribute-driven console command discovery on Symfony Console. |
| [`phpdot/container`](packages/container) | Server-agnostic service scoping for PHP-DI |
| [`phpdot/container-swoole`](packages/container-swoole) | Swoole context provider and per-request dispatcher for phpdot/container |
| [`phpdot/contracts`](packages/contracts) | Shared interfaces for the PHPdot ecosystem |
| [`phpdot/database`](packages/database) | Query builder, schema management, and migrations for PHP. Built on Doctrine DBAL. |
| [`phpdot/env`](packages/env) | Typed, schema-validated, immutable .env configuration for modern PHP. |
| [`phpdot/error`](packages/error) | Structured error codes with context, translatable messages, and uniform output across every channel. |
| [`phpdot/error-handler`](packages/error-handler) | Modern error handler with debug pages, RFC 9457 JSON errors, customizable renderers, solution providers, and PSR-15 middleware. |
| [`phpdot/event`](packages/event) | PSR-14 event dispatcher with attribute-based listener discovery, async dispatch, ordering, and persistence abstraction. |
| [`phpdot/filesystem`](packages/filesystem) | Coroutine-safe, PSR-native file storage for the PHPdot ecosystem: local and S3-compatible (AWS S3, Cloudflare R2, MinIO, DigitalOcean Spaces) over a PSR-18 + SigV4 client, with typed streams, resumable chunked uploads and first-class progress. |
| [`phpdot/http`](packages/http) | PSR-7 HTTP messages, responses, and uploads for modern PHP. |
| [`phpdot/http-middleware`](packages/http-middleware) | PSR-15 middlewares for PHPdot. |
| [`phpdot/i18n`](packages/i18n) | Internationalization with ICU MessageFormat, pluggable loaders, PSR-16 caching. |
| [`phpdot/logs`](packages/logs) | Tracer, span, and pending-log core implementing the contracts observability boundary. |
| [`phpdot/mail`](packages/mail) | Coroutine-safe transactional email for the PHPdot ecosystem: a fluent, immutable message builder over any symfony/mailer transport. |
| [`phpdot/mongodb`](packages/mongodb) | Resilient MongoDB client with fluent CRUD builders, Document object, exception translation, and query logging. |
| [`phpdot/package`](packages/package) | Package discovery and definition loading for the PHPdot container. |
| [`phpdot/path`](packages/path) | Project-root discovery and named path resolution for PHPdot, configured via phpdot/config. |
| [`phpdot/pool`](packages/pool) | Generic coroutine-safe connection pool for Swoole. Holds any object. Channel-based with idle cleanup, optional heartbeat, and leak prevention. |
| [`phpdot/psr3-bridge`](packages/psr3-bridge) | PSR-3 logger bridge into the PHPdot writer pipeline. |
| [`phpdot/qrcode`](packages/qrcode) | Coroutine-safe QR code generation with SVG, PNG and data-URI renderers for the PHPdot ecosystem. |
| [`phpdot/rabbitmq`](packages/rabbitmq) | RabbitMQ client for PHP: publish, consume, retry, dead letter, topology. |
| [`phpdot/realtime`](packages/realtime) | Real-time WebSocket engine — rooms, channels, presence, broadcast. Transport-agnostic: depends only on phpdot/contracts, never a concrete server. |
| [`phpdot/redis`](packages/redis) | Coroutine-safe Redis client wrapping ext-redis with auto-reconnect, exponential backoff, exception translation, and a pool connector for phpdot/pool. |
| [`phpdot/routing`](packages/routing) | High-performance segment-trie routing for PHP. PSR-7/15/17 compliant. |
| [`phpdot/routing-rt`](packages/routing-rt) | Real-time routing for WebSocket and SSE — extends phpdot/routing. |
| [`phpdot/server`](packages/server) | Swoole HTTP, WebSocket, and TCP server for PSR-15 handlers. |
| [`phpdot/session`](packages/session) | Secure session management with pluggable handlers, flash data, CSRF tokens, and PSR-15 middleware. |
| [`phpdot/sheets`](packages/sheets) | Fast, streaming, low-memory XLSX reader and writer with charts, images, conditional formatting and data validation as opt-in plugins. |
| [`phpdot/template`](packages/template) | Swoole-safe Twig integration with auto-discovered extensions for the PHPdot ecosystem. |
| [`phpdot/totp`](packages/totp) | Coroutine-safe, zero-dependency HOTP/TOTP (RFC 4226 / RFC 6238) with provisioning URIs for the PHPdot ecosystem. |
| [`phpdot/tracelog`](packages/tracelog) | Channel-based log backend: handlers, formatters, and fail-closed record encryption. |
| [`phpdot/validator`](packages/validator) | Strict, type-safe validation with structured error codes for the PHPdot ecosystem. |

## How it works

- Packages live in `packages/<name>/`, each a complete Composer package.
- One vendor, one PHPStan (level 10 + strict), one code style, one test run for the whole tree.
- On every green push to `main`, CI splits each package's history (`git subtree split`) and
  fast-forwards it to `phpdot/<name>` — original authors, dates, and messages intact. Mirror pushes
  are never forced: a rejected push means divergence and fails loudly instead of overwriting.
- Minors stamp every package, patches tag only what changed, and published tags are immutable.
  Publishing is impossible unless all gates pass.
- The split matrix is derived from the tree on every run — a package cannot be silently left out.

## Adding a package

1. Create the empty mirror repo `phpdot/<name>` and make sure `SPLIT_TOKEN` can push to it.
2. Add `packages/<name>/` (with `composer.json`, `src/`, `tests/`, `LICENSE`, `README.md`) and map
   it in the root `composer.json` (`autoload`, `autoload-dev`, `replace`) — the manifest gate fails
   otherwise.
3. Merge to `main` — CI populates the mirror on the next push.

## Development

```bash
composer install
composer check   # phpunit + phpstan + php-cs-fixer, whole tree
```

See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

MIT
