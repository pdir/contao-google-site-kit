<?php

declare(strict_types=1);

/*
 * This file is part of pdir/contao-site-kit.
 *
 * (c) pdir
 *
 * @license LGPL-3.0-or-later
 */

namespace Pdir\SiteKitBundle\Google;

/**
 * Encrypts secrets (refresh token, client secret) before they are written to the
 * database. The key is derived from the application secret (APP_SECRET), so a
 * database dump alone does not reveal the Google credentials.
 */
class Crypto
{
    private const PREFIX = 'sk1:';

    private string $key;

    public function __construct(string $secret)
    {
        if ('' === $secret) {
            throw new \InvalidArgumentException('The kernel secret must not be empty.');
        }

        $this->key = sodium_crypto_generichash('pdir_site_kit|'.$secret, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    public function encrypt(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return self::PREFIX.sodium_bin2base64($nonce.sodium_crypto_secretbox($plain, $nonce, $this->key), SODIUM_BASE64_VARIANT_ORIGINAL);
    }

    /**
     * Returns null if the value cannot be decrypted (e.g. because APP_SECRET changed).
     */
    public function decrypt(string $encrypted): string|null
    {
        if (!str_starts_with($encrypted, self::PREFIX)) {
            return null;
        }

        try {
            $raw = sodium_base642bin(substr($encrypted, \strlen(self::PREFIX)), SODIUM_BASE64_VARIANT_ORIGINAL);
        } catch (\SodiumException) {
            return null;
        }

        if (\strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }

        $plain = sodium_crypto_secretbox_open(
            substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $this->key,
        );

        return false === $plain ? null : $plain;
    }
}
