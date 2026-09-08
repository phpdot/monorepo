<?php

declare(strict_types=1);

namespace PHPdot\WebAuthn\Tests\Support;

use function base64_decode;

use CBOR\Encoder;

use function hash;
use function json_encode;
use function openssl_pkey_export;
use function openssl_pkey_new;
use function openssl_sign;

use OpenSSLCertificate;

use function pack;

use PHPdot\WebAuthn\Base64Url;

use function random_bytes;
use function str_repeat;
use function strlen;
use function substr;

/**
 * A real authenticator, in test clothing: one ES256 (P-256) key pair, one
 * stable credential id, a packed self-attestation for registration, and
 * signed assertions — the same bytes a security key sends, built with
 * openssl and CBOR so the ceremonies under test verify genuine cryptography.
 */
final class AuthenticatorSimulator
{
    private readonly string $credentialId;

    private readonly string $publicKeyDer;

    private readonly OpenSSLCertificate|\OpenSSLAsymmetricKey $key;

    /**
     * @param string $origin The origin ceremonies run on
     * @param string $rpId The relying party id
     */
    public function __construct(
        private readonly string $origin,
        private readonly string $rpId,
    ) {
        $this->credentialId = random_bytes(32);
        $this->key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $pem = '';
        openssl_pkey_export($this->key, $pem);
        $this->publicKeyDer = (string) base64_decode((string) preg_replace('/-----.*-----|\s+/', '', $pem), true);
    }

    /**
     * A registration response for this simulator's credential under the
     * challenge, reporting the given signature counter.
     *
     * @param string $challenge The raw challenge bytes from the options
     * @param int $signCount The counter embedded in the attestation auth data
     *
     * @return string The credential JSON the browser would post
     */
    public function attestation(string $challenge, int $signCount): string
    {
        $clientDataJson = $this->clientData('webauthn.create', $challenge);
        $authData = $this->authenticatorData($signCount, withCredential: true);
        $signature = $this->sign($authData . hash('sha256', $clientDataJson, true));

        $attestationObject = (new Encoder())->encode([
            'fmt' => 'packed',
            'attStmt' => ['alg' => -7, 'sig' => $signature],
            'authData' => $authData,
        ]);

        return $this->credentialJson([
            'clientDataJSON' => Base64Url::encode($clientDataJson),
            'attestationObject' => Base64Url::encode($attestationObject),
        ]);
    }

    /**
     * An assertion response under the challenge, advancing the counter.
     *
     * @param string $challenge The raw challenge bytes from the options
     * @param int $signCount The counter the authenticator reports (must exceed the stored one)
     *
     * @return string The credential JSON the browser would post
     */
    public function assertion(string $challenge, int $signCount): string
    {
        $clientDataJson = $this->clientData('webauthn.get', $challenge);
        $authData = $this->authenticatorData($signCount, withCredential: false);
        $signature = $this->sign($authData . hash('sha256', $clientDataJson, true));

        return $this->credentialJson([
            'clientDataJSON' => Base64Url::encode($clientDataJson),
            'authenticatorData' => Base64Url::encode($authData),
            'signature' => Base64Url::encode($signature),
        ]);
    }

    /**
     * The credential id this simulator registers under.
     *
     * @return string Raw bytes
     */
    public function credentialId(): string
    {
        return $this->credentialId;
    }

    /**
     * @param array<string, string> $response
     *
     * @return string
     */
    private function credentialJson(array $response): string
    {
        return (string) json_encode([
            'id' => Base64Url::encode($this->credentialId),
            'rawId' => Base64Url::encode($this->credentialId),
            'type' => 'public-key',
            'response' => $response,
        ]);
    }

    /**
     * @param string $type webauthn.create or webauthn.get
     * @param string $challenge The raw challenge bytes
     *
     * @return string
     */
    private function clientData(string $type, string $challenge): string
    {
        return (string) json_encode([
            'type' => $type,
            'challenge' => Base64Url::encode($challenge),
            'origin' => $this->origin,
        ]);
    }

    /**
     * @param int $signCount The reported signature counter
     * @param bool $withCredential Append attested credential data (registration shape)
     *
     * @return string
     */
    private function authenticatorData(int $signCount, bool $withCredential): string
    {
        $data = hash('sha256', $this->rpId, true)
            . pack('C', $withCredential ? 0x45 : 0x05)
            . pack('N', $signCount);

        if (!$withCredential) {
            return $data;
        }

        $point = substr($this->publicKeyDer, -65);
        $coseKey = (new Encoder())->encode([
            1 => 2,
            3 => -7,
            -1 => 1,
            -2 => substr($point, 1, 32),
            -3 => substr($point, 33, 32),
        ]);

        return $data
            . str_repeat("\0", 16)
            . pack('n', strlen($this->credentialId))
            . $this->credentialId
            . $coseKey;
    }

    /**
     * @param string $bytes The bytes to sign
     *
     * @return string
     */
    private function sign(string $bytes): string
    {
        $signature = '';
        openssl_sign($bytes, $signature, $this->key, OPENSSL_ALGO_SHA256);

        return $signature;
    }
}
