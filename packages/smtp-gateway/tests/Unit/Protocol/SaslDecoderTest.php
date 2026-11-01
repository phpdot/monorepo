<?php

declare(strict_types=1);

namespace PHPdot\Mail\Gateway\Tests\Unit\Protocol;

use PHPdot\Mail\Gateway\Auth\SaslDecoder;
use PHPdot\Mail\Gateway\DataType\Enum\AuthMechanism;
use PHPdot\Mail\Gateway\Exception\AuthenticationException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SaslDecoderTest extends TestCase
{
    private SaslDecoder $decoder;

    protected function setUp(): void
    {
        $this->decoder = new SaslDecoder();
    }

    #[Test]
    public function decodesPlainToken(): void
    {
        // \0username\0password
        $token = base64_encode("\0omar\0secret");
        $creds = $this->decoder->decodePlain($token);

        self::assertSame(AuthMechanism::Plain, $creds->mechanism);
        self::assertSame('omar', $creds->username);
        self::assertSame('secret', $creds->password);
    }

    #[Test]
    public function decodesPlainWithAuthzid(): void
    {
        // authzid\0authcid\0password
        $token = base64_encode("admin\0omar\0secret");
        $creds = $this->decoder->decodePlain($token);

        self::assertSame('omar', $creds->username);
        self::assertSame('secret', $creds->password);
    }

    #[Test]
    public function plainRejectsInvalidBase64(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->decoder->decodePlain('not!valid!base64!!!');
    }

    #[Test]
    public function plainRejectsWrongPartCount(): void
    {
        $this->expectException(AuthenticationException::class);
        $token = base64_encode("onlyonepart");
        $this->decoder->decodePlain($token);
    }

    #[Test]
    public function plainRejectsEmptyUsername(): void
    {
        $this->expectException(AuthenticationException::class);
        $token = base64_encode("\0\0password");
        $this->decoder->decodePlain($token);
    }

    #[Test]
    public function decodesLoginCredentials(): void
    {
        $user = base64_encode('omar');
        $pass = base64_encode('secret');
        $creds = $this->decoder->decodeLogin($user, $pass);

        self::assertSame(AuthMechanism::Login, $creds->mechanism);
        self::assertSame('omar', $creds->username);
        self::assertSame('secret', $creds->password);
    }

    #[Test]
    public function loginRejectsInvalidBase64(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->decoder->decodeLogin('bad!!!', base64_encode('pass'));
    }

    #[Test]
    public function decodesXOAuth2Token(): void
    {
        $token = base64_encode("user=omar@gmail.com\x01auth=Bearer ya29.token123\x01\x01");
        $creds = $this->decoder->decodeXOAuth2($token);

        self::assertSame(AuthMechanism::XOAuth2, $creds->mechanism);
        self::assertSame('omar@gmail.com', $creds->username);
        self::assertSame('ya29.token123', $creds->password);
    }

    #[Test]
    public function xoauth2RejectsInvalidFormat(): void
    {
        $this->expectException(AuthenticationException::class);
        $token = base64_encode("invalid format");
        $this->decoder->decodeXOAuth2($token);
    }
}
