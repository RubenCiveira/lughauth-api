<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\TokenSecurity\Infrastructure\Driven;

use Override;
use Ramsey\Uuid\Uuid;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\KeyPair;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\Gateway\KeyPairGenerator;

/**
 * Generates RS256 key pairs (RSA 4096) with PHP's openssl extension.
 * OpenSSL errors are reported in the exception message, never written to the output.
 */
class OpenSslKeyPairGenerator implements KeyPairGenerator
{
    private const int RSA_BITS = 4096;

    #[Override]
    public function generate(): KeyPair
    {
        $resource = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => self::RSA_BITS,
        ]);
        $details = $resource === false ? false : openssl_pkey_get_details($resource);
        if ($resource === false || $details === false || !openssl_pkey_export($resource, $privateKey)) {
            $errors = [];
            while ($message = openssl_error_string()) {
                $errors[] = $message;
            }
            throw new \RuntimeException('Cannot generate RSA key pair: ' . implode('; ', $errors));
        }
        return new KeyPair(
            kid: Uuid::uuid4()->toString(),
            alg: 'RS256',
            keyUse: 'sig',
            privateKey: $privateKey,
            publicKey: $details['key']
        );
    }
}
