<?php

declare(strict_types=1);

namespace PHPdot\Totp\Tests\Unit;

use PHPdot\Totp\Enum\Algorithm;
use PHPUnit\Framework\TestCase;

final class AlgorithmTest extends TestCase
{
    public function test_values_are_hash_hmac_names(): void
    {
        foreach (Algorithm::cases() as $algorithm) {
            self::assertContains($algorithm->value, hash_hmac_algos());
        }
    }

    public function test_label_is_uppercase(): void
    {
        self::assertSame('SHA1', Algorithm::Sha1->label());
        self::assertSame('SHA256', Algorithm::Sha256->label());
        self::assertSame('SHA512', Algorithm::Sha512->label());
    }
}
