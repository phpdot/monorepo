<?php

declare(strict_types=1);

namespace PHPdot\TraceLog\Tests\Encryption;

use PHPdot\TraceLog\Encryption\ChaChaEncryptor;
use PHPdot\TraceLog\Exception\EncryptionException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ChaChaEncryptorTest extends TestCase
{
    #[Test]
    public function encryptDecryptRoundtrip(): void
    {
        $key = ChaChaEncryptor::generateKey();
        $encryptor = new ChaChaEncryptor($key);

        $plaintext = 'Hello, secret world!';
        $ciphertext = $encryptor->encrypt($plaintext);
        $decrypted = $encryptor->decrypt($ciphertext);

        self::assertSame($plaintext, $decrypted);
    }

    #[Test]
    public function generateKeyReturnsBase64String(): void
    {
        $key = ChaChaEncryptor::generateKey();

        self::assertNotEmpty($key);

        $decoded = base64_decode($key, true);
        self::assertNotFalse($decoded);
        self::assertSame(32, strlen($decoded));
    }

    #[Test]
    public function invalidKeyThrows(): void
    {
        $this->expectException(EncryptionException::class);

        new ChaChaEncryptor('not-a-valid-base64-key');
    }

    #[Test]
    public function shortKeyThrows(): void
    {
        $this->expectException(EncryptionException::class);

        new ChaChaEncryptor(base64_encode('short'));
    }

    #[Test]
    public function differentPlaintextsProduceDifferentCiphertexts(): void
    {
        $key = ChaChaEncryptor::generateKey();
        $encryptor = new ChaChaEncryptor($key);

        $cipher1 = $encryptor->encrypt('message one');
        $cipher2 = $encryptor->encrypt('message two');

        self::assertNotSame($cipher1, $cipher2);
    }

    #[Test]
    public function samePlaintextProducesDifferentCiphertexts(): void
    {
        $key = ChaChaEncryptor::generateKey();
        $encryptor = new ChaChaEncryptor($key);

        $cipher1 = $encryptor->encrypt('same message');
        $cipher2 = $encryptor->encrypt('same message');

        // Due to random nonce, ciphertexts should differ
        self::assertNotSame($cipher1, $cipher2);
    }

    #[Test]
    public function tamperedCiphertextThrowsOnDecrypt(): void
    {
        $key = ChaChaEncryptor::generateKey();
        $encryptor = new ChaChaEncryptor($key);

        $ciphertext = $encryptor->encrypt('original message');

        // Tamper with the ciphertext
        $decoded = base64_decode($ciphertext, true);
        self::assertNotFalse($decoded);

        $tampered = $decoded;
        $tampered[strlen($tampered) - 1] = chr(ord($tampered[strlen($tampered) - 1]) ^ 0xFF);
        $tamperedEncoded = base64_encode($tampered);

        $this->expectException(EncryptionException::class);
        $encryptor->decrypt($tamperedEncoded);
    }

    #[Test]
    public function encryptDecryptEmptyString(): void
    {
        $key = ChaChaEncryptor::generateKey();
        $encryptor = new ChaChaEncryptor($key);

        $ciphertext = $encryptor->encrypt('');
        $decrypted = $encryptor->decrypt($ciphertext);

        self::assertSame('', $decrypted);
    }

    #[Test]
    public function encryptDecryptUnicodeText(): void
    {
        $key = ChaChaEncryptor::generateKey();
        $encryptor = new ChaChaEncryptor($key);

        $plaintext = "Hello \xC3\xA9\xC3\xA0\xC3\xBC \xE4\xB8\xAD\xE6\x96\x87 \xF0\x9F\x9A\x80";
        $ciphertext = $encryptor->encrypt($plaintext);
        $decrypted = $encryptor->decrypt($ciphertext);

        self::assertSame($plaintext, $decrypted);
    }

    #[Test]
    public function encryptDecryptLargePayload(): void
    {
        $key = ChaChaEncryptor::generateKey();
        $encryptor = new ChaChaEncryptor($key);

        $plaintext = str_repeat('A', 10240);
        $ciphertext = $encryptor->encrypt($plaintext);
        $decrypted = $encryptor->decrypt($ciphertext);

        self::assertSame($plaintext, $decrypted);
    }

    #[Test]
    public function decryptWithWrongKeyThrows(): void
    {
        $key1 = ChaChaEncryptor::generateKey();
        $key2 = ChaChaEncryptor::generateKey();

        $encryptor1 = new ChaChaEncryptor($key1);
        $encryptor2 = new ChaChaEncryptor($key2);

        $ciphertext = $encryptor1->encrypt('secret data');

        $this->expectException(EncryptionException::class);
        $encryptor2->decrypt($ciphertext);
    }

    #[Test]
    public function ciphertextLengthDoesNotRevealCompressibility(): void
    {
        $encryptor = new ChaChaEncryptor(ChaChaEncryptor::generateKey());

        // No pre-encryption compression (CRIME/BREACH): a highly compressible plaintext and an
        // incompressible one of the same length must produce equal-length ciphertext.
        $compressible = (string) base64_decode($encryptor->encrypt(str_repeat('A', 4096)), true);
        $incompressible = (string) base64_decode($encryptor->encrypt(random_bytes(4096)), true);

        self::assertSame(strlen($incompressible), strlen($compressible));
    }
}
