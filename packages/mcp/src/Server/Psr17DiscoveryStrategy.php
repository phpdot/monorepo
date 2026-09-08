<?php

declare(strict_types=1);

namespace PHPdot\Mcp\Server;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Strategy\DiscoveryStrategy;
use PHPdot\Http\Factory\ResponseFactory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Names phpdot/http's factory to php-http/discovery.
 *
 * StreamableHttpTransport takes the PSR-17 factories the gateway injects, but its
 * handshake leg reaches them through a static self::handshakeMiddleware() that
 * builds ProtocolVersionMiddleware with no arguments — so that one leg falls back
 * to discovery and never sees what was passed. Inside the monorepo discovery finds
 * a bundled implementation by chance; a standalone consumer has none, and every
 * handshake-era request fails with "No PSR-17 response factory found". Registering
 * the factory this package already requires closes the gap without a second PSR-7
 * implementation in the tree. Delete once the SDK threads its factories through.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class Psr17DiscoveryStrategy implements DiscoveryStrategy
{
    /**
     * Put this strategy in front of discovery's own.
     */
    public static function register(): void
    {
        Psr17FactoryDiscovery::prependStrategy(self::class);
    }

    /**
     * @param string $type The PSR-17 interface discovery is resolving
     *
     * @return list<array{class: class-string, condition: class-string}>
     */
    public static function getCandidates($type): array
    {
        if ($type === ResponseFactoryInterface::class || $type === StreamFactoryInterface::class) {
            return [['class' => ResponseFactory::class, 'condition' => ResponseFactory::class]];
        }

        return [];
    }
}
