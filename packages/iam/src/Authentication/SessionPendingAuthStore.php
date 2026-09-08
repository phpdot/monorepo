<?php

declare(strict_types=1);

/**
 * Session-backed PendingAuthStoreInterface.
 *
 * Mirrors SessionAuthenticator: the identity is stored as id + type and rebuilt
 * through the IdentityReconstructorInterface (never serialized), and the keys are
 * namespaced under `_pending_auth.*` — kept entirely separate from the
 * authenticator's `_auth.*` so a half-finished authentication can never be read
 * back as a full login.
 *
 * Reads fail closed: any missing, unreconstructable or inconsistent state is
 * treated as "no pending", forcing a fresh attempt rather than a partial login.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authentication;

use DateTimeImmutable;
use DateTimeInterface;
use PHPdot\Container\Attribute\Binds;
use PHPdot\Container\Attribute\Scoped;
use PHPdot\Contracts\Session\SessionInterface;
use PHPdot\Iam\Authentication\Contract\IdentityReconstructorInterface;
use PHPdot\Iam\Authentication\Contract\PendingAuthStoreInterface;

#[Scoped]
#[Binds(PendingAuthStoreInterface::class)]
final readonly class SessionPendingAuthStore implements PendingAuthStoreInterface
{
    private const string ID_KEY = '_pending_auth.id';

    private const string TYPE_KEY = '_pending_auth.type';

    private const string SATISFIED_KEY = '_pending_auth.satisfied';

    private const string REQUIREMENTS_KEY = '_pending_auth.requirements';

    private const string ATTRIBUTES_KEY = '_pending_auth.attributes';

    private const string EXPIRES_KEY = '_pending_auth.expires';

    private const string PROVEN_KEY = '_pending_auth.proven';

    public function __construct(
        private SessionInterface $session,
        private IdentityReconstructorInterface $reconstructor,
    ) {}

    public function put(PendingAuth $pending): void
    {
        $this->session->set(self::ID_KEY, $pending->identity->id());
        $this->session->set(self::TYPE_KEY, $pending->identity->type());
        $this->session->set(self::SATISFIED_KEY, $pending->satisfied);
        $this->session->set(self::REQUIREMENTS_KEY, $this->encode($pending->requirements));
        $this->session->set(self::ATTRIBUTES_KEY, $pending->attributes);
        $this->session->set(self::PROVEN_KEY, $pending->provenFactors);

        if ($pending->expiresAt !== null) {
            $this->session->set(self::EXPIRES_KEY, $pending->expiresAt->format(DateTimeInterface::ATOM));
        }
    }

    public function get(): null|PendingAuth
    {
        if (!$this->session->has(self::ID_KEY)) {
            return null;
        }

        $id = $this->session->get(self::ID_KEY);
        $type = $this->session->get(self::TYPE_KEY);

        if (($id !== null && !is_int($id) && !is_string($id)) || !is_string($type)) {
            return null;
        }

        $identity = $this->reconstructor->reconstruct($id, $type);
        if ($identity === null) {
            return null;
        }

        $requirements = $this->decode($this->session->get(self::REQUIREMENTS_KEY));
        $satisfied = $this->session->get(self::SATISFIED_KEY);

        if ($requirements->isEmpty() || !is_int($satisfied) || $satisfied < 0 || $satisfied > $requirements->count()) {
            return null;
        }

        $attributes = $this->stringKeyed($this->session->get(self::ATTRIBUTES_KEY));

        $expiresAt = null;
        $rawExpires = $this->session->get(self::EXPIRES_KEY);

        if (is_string($rawExpires) && $rawExpires !== '') {
            $parsed = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $rawExpires);
            $expiresAt = $parsed === false ? null : $parsed;
        }

        $rawProven = $this->session->get(self::PROVEN_KEY);
        $proven = [];

        if (is_array($rawProven)) {
            foreach ($rawProven as $factor) {
                if (is_string($factor) && $factor !== '') {
                    $proven[] = $factor;
                }
            }
        }

        return new PendingAuth(
            $identity,
            $requirements,
            $satisfied,
            $attributes,
            $expiresAt,
            $proven,
        );
    }

    public function clear(): void
    {
        foreach ([
            self::ID_KEY,
            self::TYPE_KEY,
            self::SATISFIED_KEY,
            self::REQUIREMENTS_KEY,
            self::ATTRIBUTES_KEY,
            self::EXPIRES_KEY,
            self::PROVEN_KEY,
        ] as $key) {
            $this->session->remove($key);
        }
    }

    /**
     * @return list<array{factor: string, params: array<string, mixed>}>
     */
    private function encode(RequirementSet $requirements): array
    {
        $encoded = [];

        foreach ($requirements->requirements as $requirement) {
            $encoded[] = ['factor' => $requirement->factor, 'params' => $requirement->params];
        }

        return $encoded;
    }

    private function decode(mixed $raw): RequirementSet
    {
        if (!is_array($raw)) {
            return RequirementSet::none();
        }

        $requirements = [];

        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }

            $factor = $item['factor'] ?? null;
            if (!is_string($factor) || $factor === '') {
                continue;
            }

            $requirements[] = new Requirement($factor, $this->stringKeyed($item['params'] ?? null));
        }

        return new RequirementSet($requirements);
    }

    /**
     * Rebuild an untrusted session value as a string-keyed map, dropping any
     * non-string keys — yields array<string, mixed> for the typed constructors.
     *
     * @return array<string, mixed>
     */
    private function stringKeyed(mixed $raw): array
    {
        $result = [];

        if (is_array($raw)) {
            foreach ($raw as $key => $value) {
                if (is_string($key)) {
                    $result[$key] = $value;
                }
            }
        }

        return $result;
    }
}
