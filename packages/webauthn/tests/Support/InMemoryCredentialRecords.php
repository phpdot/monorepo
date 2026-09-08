<?php

declare(strict_types=1);

namespace PHPdot\WebAuthn\Tests\Support;

use PHPdot\WebAuthn\Contract\CredentialRecordRepositoryInterface;

/**
 * In-memory credential storage: credential id => [userHandle, recordJson].
 */
final class InMemoryCredentialRecords implements CredentialRecordRepositoryInterface
{
    /**
     * @var array<string, array{user: string, record: string}>
     */
    public array $rows = [];

    public function findByCredentialId(string $credentialId): null|string
    {
        return $this->rows[$credentialId]['record'] ?? null;
    }

    public function recordsForUserHandle(string $userHandle): array
    {
        $records = [];

        foreach ($this->rows as $row) {
            if ($row['user'] === $userHandle) {
                $records[] = $row['record'];
            }
        }

        return $records;
    }

    public function exists(string $credentialId): bool
    {
        return isset($this->rows[$credentialId]);
    }

    public function save(string $credentialId, string $recordJson, string $userHandle): void
    {
        $this->rows[$credentialId] = ['user' => $userHandle, 'record' => $recordJson];
    }
}
