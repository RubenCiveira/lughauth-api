<?php

// verify openssl
$privateKeyResource = openssl_pkey_new([
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
    'private_key_bits' => 4096,
]);
if ($privateKeyResource === false) {
    while ($msg = openssl_error_string()) {
        echo "OpenSSL error: $msg\n";
    }
    throw new \RuntimeException('No se pudo generar la clave RSA');
}
if (!in_array('aes-256-gcm', openssl_get_cipher_methods(true), true)) {
    throw new \RuntimeException('ERROR: AES-256-GCM not supported by your OpenSSL build.');
}

if (!extension_loaded('intl')) {
    echo "La extensión intl no está habilitada.";
} else {
    echo "intl está habilitada.";
}