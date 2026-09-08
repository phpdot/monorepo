<?php

declare(strict_types=1);

/**
 * Credential storage, owned by the host. Speaks JSON strings end to end —
 * the package serializes and deserializes Webauthn\CredentialRecord through
 * its own serializer, so the host's whole burden is a JSON column keyed by
 * credential id and indexed by user handle. Terms: credential ids are unique
 * across all users; a record saved after every authentication keeps its
 * counter (the clone detector) current; payloads are opaque to the host.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\WebAuthn\Contract;

interface CredentialRecordRepositoryInterface
{
    /**
     * The stored record JSON for a credential id, null when unknown.
     *
     * @param string $credentialId The credential id (base64url)
     *
     * @return string|null
     */
    public function findByCredentialId(string $credentialId): null|string;

    /**
     * Every stored record JSON belonging to a user handle.
     *
     * @param string $userHandle The user handle (raw bytes, base64url-encoded)
     *
     * @return list<string>
     */
    public function recordsForUserHandle(string $userHandle): array;

    /**
     * Whether the credential id is already registered — the duplicate guard
     * the library itself never performs.
     *
     * @param string $credentialId The credential id (base64url)
     *
     * @return bool
     */
    public function exists(string $credentialId): bool;

    /**
     * Persist a record. Called on registration and after EVERY authentication.
     *
     * @param string $credentialId The credential id (base64url)
     * @param string $recordJson The serialized CredentialRecord
     * @param string $userHandle The owning user handle (base64url)
     *
     * @return void
     */
    public function save(string $credentialId, string $recordJson, string $userHandle): void;
}
