<?php

declare(strict_types=1);

/**
 * Session lifecycle orchestrator.
 *
 * Coordinates session start, save, and destroy operations between
 * the handler (storage), serializer, and configuration.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Session;

use PHPdot\Container\Attribute\Scoped;
use PHPdot\Contracts\Session\SerializerInterface;
use PHPdot\Contracts\Session\SessionHandlerInterface;
use PHPdot\Session\Exception\SessionExpiredException;
use PHPdot\Session\Serializer\JsonSerializer;

#[Scoped]
final class SessionManager
{
    /**
     * Wire the manager to its storage handler, config, and serializer.
     *
     * @param SessionHandlerInterface $handler
     * @param SessionConfig $config
     * @param SerializerInterface $serializer
     */
    public function __construct(
        private readonly SessionHandlerInterface $handler,
        private readonly SessionConfig $config,
        private readonly SerializerInterface $serializer = new JsonSerializer(),
    ) {}

    /**
     * Start a session: resume an existing one or create a new one.
     *
     * Runs probabilistic garbage collection before loading.
     *
     * @param string|null $cookieId The session ID from the cookie, or null for a new session.
     *
     * @throws SessionExpiredException If the session has expired.
     *
     * @return Session
     */
    public function start(?string $cookieId): Session
    {
        $this->maybeGc();

        if ($cookieId !== null && $cookieId !== '') {
            try {
                $id = SessionId::fromString($cookieId);
            } catch (\Throwable) {
                return $this->createNew();
            }

            if ($this->handler->exists($id->toString())) {
                return $this->resume($id);
            }
        }

        return $this->createNew();
    }

    /**
     * Save session data to the handler.
     *
     * Updates last activity, ages flash data, serializes, and writes.
     *
     * @param Session $session
     *
     * @return void
     */
    public function save(Session $session): void
    {
        $session->setLastActivity(time());
        $session->ageFlash();

        $data = $session->toArray();
        $encoded = $this->serializer->encode($data);

        $this->handler->write(
            $session->id(),
            $encoded,
            $this->config->lifetime,
        );
    }

    /**
     * Destroy a session by ID.
     *
     * @param string $id
     *
     * @return void
     */
    public function destroy(string $id): void
    {
        $this->handler->destroy($id);
    }

    /**
     * Build the Set-Cookie header value for the session cookie.
     *
     * @param Session $session
     *
     * @return string
     */
    public function cookieHeader(Session $session): string
    {
        $parts = [\sprintf('%s=%s', $this->config->name, $session->id())];

        if ($this->config->path !== '') {
            $parts[] = \sprintf('Path=%s', $this->config->path);
        }

        if ($this->config->domain !== '') {
            $parts[] = \sprintf('Domain=%s', $this->config->domain);
        }

        if ($this->config->lifetime > 0) {
            $parts[] = \sprintf('Max-Age=%d', $this->config->lifetime);
        }

        if ($this->config->secure) {
            $parts[] = 'Secure';
        }

        if ($this->config->httpOnly) {
            $parts[] = 'HttpOnly';
        }

        if ($this->config->sameSite !== '') {
            $parts[] = \sprintf('SameSite=%s', $this->config->sameSite);
        }

        if ($this->config->partitioned) {
            $parts[] = 'Partitioned';
        }

        return implode('; ', $parts);
    }

    /**
     * Build a Set-Cookie header that expires/removes the session cookie.
     *
     * @return string
     */
    public function expireCookieHeader(): string
    {
        $parts = [\sprintf('%s=', $this->config->name)];
        $parts[] = 'Max-Age=0';

        if ($this->config->path !== '') {
            $parts[] = \sprintf('Path=%s', $this->config->path);
        }

        if ($this->config->domain !== '') {
            $parts[] = \sprintf('Domain=%s', $this->config->domain);
        }

        return implode('; ', $parts);
    }

    /**
     * Get the session configuration.
     *
     * @return SessionConfig
     */
    public function getConfig(): SessionConfig
    {
        return $this->config;
    }

    /**
     * Resume an existing session from the handler.
     *
     * @param SessionId $id
     *
     * @throws SessionExpiredException If the session has exceeded the idle timeout.
     *
     * @return Session
     */
    private function resume(SessionId $id): Session
    {
        $raw = $this->handler->read($id->toString());
        $payload = $this->serializer->decode($raw);

        $createdAt = \is_int($payload['created_at'] ?? null) ? $payload['created_at'] : time();
        $lastActivity = \is_int($payload['last_activity'] ?? null) ? $payload['last_activity'] : time();

        if ($this->config->lifetime > 0 && (time() - $lastActivity) > $this->config->lifetime) {
            $this->handler->destroy($id->toString());

            throw new SessionExpiredException(
                'Session has expired due to inactivity.',
                $id->toString(),
            );
        }

        $session = new Session($id, $createdAt, $lastActivity);
        $session->load($payload);

        return $session;
    }

    /**
     * Create a brand new session.
     *
     * @return Session
     */
    private function createNew(): Session
    {
        $now = time();
        $id = SessionId::generate();
        $session = new Session($id, $now, $now);
        $session->load([]);

        return $session;
    }

    /**
     * Run garbage collection based on configured probability.
     *
     * @return void
     */
    private function maybeGc(): void
    {
        if ($this->config->gcProbability <= 0) {
            return;
        }

        if (random_int(1, 100) <= $this->config->gcProbability) {
            $this->handler->gc($this->config->lifetime);
        }
    }
}
