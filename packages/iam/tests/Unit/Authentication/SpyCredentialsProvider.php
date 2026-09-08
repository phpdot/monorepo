<?php

declare(strict_types=1);

namespace PHPdot\Iam\Tests\Unit\Authentication;

use PHPdot\Iam\Authentication\Contract\CredentialsProviderInterface;
use PHPdot\Iam\Authentication\StoredCredentials;

/**
 * CredentialsProviderInterface double that returns a fixed record (or null), so the
 * password stage's known-user / unknown-user paths can be exercised.
 */
final class SpyCredentialsProvider implements CredentialsProviderInterface
{
    public function __construct(public null|StoredCredentials $stored = null) {}

    public function findByIdentifier(string $identifier): null|StoredCredentials
    {
        return $this->stored;
    }
}
