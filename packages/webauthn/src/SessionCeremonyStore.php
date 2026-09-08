<?php

declare(strict_types=1);

/**
 * Session-backed ceremony state, namespaced under `_webauthn.ceremony.*` —
 * read-once and TTL-bound. The library only compares challenges; consuming
 * them here is the replay protection, and the TTL prunes half-finished
 * ceremonies instead of letting them hang. A TTL-expired state answers null
 * on consume — to the ceremony that is indistinguishable from unknown, which
 * is exactly the fail-closed shape wanted.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\WebAuthn;

use PHPdot\Container\Attribute\Binds;
use PHPdot\Container\Attribute\Scoped;
use PHPdot\Contracts\Session\SessionInterface;
use PHPdot\WebAuthn\Contract\CeremonyStoreInterface;
use Psr\Clock\ClockInterface;

#[Scoped]
#[Binds(CeremonyStoreInterface::class)]
final class SessionCeremonyStore implements CeremonyStoreInterface
{
    private const string KEY_PREFIX = '_webauthn.ceremony.';

    public function __construct(
        private readonly SessionInterface $session,
        private readonly WebauthnConfig $config,
        private readonly ClockInterface $clock,
    ) {}

    public function save(string $challenge, string $stateJson): void
    {
        $this->pruneExpired();

        $this->session->set(
            self::KEY_PREFIX . $challenge,
            ['expires' => $this->clock->now()->getTimestamp() + $this->config->ceremonyTtl, 'state' => $stateJson],
        );
    }

    public function consume(string $challenge): null|string
    {
        $key = self::KEY_PREFIX . $challenge;

        if (!$this->session->has($key)) {
            return null;
        }

        $parked = $this->parkedState($this->session->get($key));
        $this->session->remove($key);

        if ($parked === null || $parked['expires'] < $this->clock->now()->getTimestamp()) {
            return null;
        }

        return $parked['state'];
    }

    /**
     * A parked state read back from storage is trusted only in shape.
     *
     * @param mixed $parked Whatever the session returned
     *
     * @return array{expires: int, state: string}|null
     */
    private function parkedState(mixed $parked): null|array
    {
        if (is_array($parked)
            && is_int($parked['expires'] ?? null)
            && is_string($parked['state'] ?? null)
        ) {
            return ['expires' => $parked['expires'], 'state' => $parked['state']];
        }

        return null;
    }

    /**
     * Drop every state whose TTL has passed — a ceremony that hangs is not a
     * ceremony that survives.
     */
    private function pruneExpired(): void
    {
        $now = $this->clock->now()->getTimestamp();
        $prefixLength = strlen(self::KEY_PREFIX);

        foreach ($this->session->all() as $key => $value) {
            $parked = $this->parkedState($value);

            if ($parked !== null
                && str_starts_with($key, self::KEY_PREFIX)
                && $parked['expires'] < $now
            ) {
                $this->session->remove($key);
            }
        }
    }
}
