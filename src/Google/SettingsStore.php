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

use Doctrine\DBAL\Connection;

/**
 * Simple key/value storage in tl_pdir_sitekit. Values flagged as secret are
 * encrypted with the application secret.
 */
class SettingsStore
{
    public const TABLE = 'tl_pdir_sitekit';

    public const CLIENT_ID = 'client_id';
    public const CLIENT_SECRET = 'client_secret';
    public const REFRESH_TOKEN = 'refresh_token';
    public const ACCOUNT_EMAIL = 'account_email';
    public const SCOPES = 'scopes';
    public const CONNECTED_AT = 'connected_at';

    /**
     * @var array<string, string|null>|null
     */
    private array|null $values = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly Crypto $crypto,
    ) {
    }

    public function get(string $name): string|null
    {
        $this->load();

        return $this->values[$name] ?? null;
    }

    public function getSecret(string $name): string|null
    {
        $value = $this->get($name);

        if (null === $value || '' === $value) {
            return null;
        }

        return $this->crypto->decrypt($value);
    }

    public function set(string $name, string|null $value): void
    {
        $this->load();

        if (null === $value) {
            $this->connection->delete(self::TABLE, ['name' => $name]);
            unset($this->values[$name]);

            return;
        }

        $exists = false !== $this->connection->fetchOne('SELECT id FROM '.self::TABLE.' WHERE name = ?', [$name]);

        if ($exists) {
            $this->connection->update(self::TABLE, ['value' => $value, 'tstamp' => time()], ['name' => $name]);
        } else {
            $this->connection->insert(self::TABLE, ['name' => $name, 'value' => $value, 'tstamp' => time()]);
        }

        $this->values[$name] = $value;
    }

    public function setSecret(string $name, string|null $value): void
    {
        $this->set($name, null === $value ? null : $this->crypto->encrypt($value));
    }

    public function clearConnection(): void
    {
        foreach ([self::REFRESH_TOKEN, self::ACCOUNT_EMAIL, self::SCOPES, self::CONNECTED_AT] as $name) {
            $this->set($name, null);
        }
    }

    private function load(): void
    {
        if (null !== $this->values) {
            return;
        }

        $this->values = [];

        try {
            $rows = $this->connection->fetchAllAssociative('SELECT name, value FROM '.self::TABLE);
        } catch (\Throwable) {
            // The table does not exist yet (database not migrated).
            return;
        }

        foreach ($rows as $row) {
            $this->values[(string) $row['name']] = null === $row['value'] ? null : (string) $row['value'];
        }
    }
}
