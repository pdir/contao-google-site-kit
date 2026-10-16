<?php

declare(strict_types=1);

/*
 * This file is part of pdir/contao-site-kit.
 *
 * (c) pdir
 *
 * @license LGPL-3.0-or-later
 */

namespace Pdir\SiteKitBundle\Site;

use Doctrine\DBAL\Connection;
use FOS\HttpCache\CacheInvalidator;

class SiteRepository
{
    public const FIELDS = [
        'sitekit_gsc_property',
        'sitekit_gsc_verification',
        'sitekit_ga4_property',
        'sitekit_ga4_measurement',
        'sitekit_gtm_container',
        'sitekit_tracking',
        'sitekit_consent_default',
    ];

    /**
     * @var array<int, SiteConfig|null>
     */
    private array $byId = [];

    public function __construct(
        private readonly Connection $connection,
        private readonly CacheInvalidator|null $cacheInvalidator = null,
    ) {
    }

    /**
     * @return list<SiteConfig>
     */
    public function findAll(): array
    {
        $rows = $this->connection->fetchAllAssociative("SELECT * FROM tl_page WHERE type = 'root' ORDER BY sorting");

        return array_map(SiteConfig::fromRow(...), $rows);
    }

    public function find(int $rootId): SiteConfig|null
    {
        if (!\array_key_exists($rootId, $this->byId)) {
            $row = $this->connection->fetchAssociative("SELECT * FROM tl_page WHERE id = ? AND type = 'root'", [$rootId]);
            $this->byId[$rootId] = false === $row ? null : SiteConfig::fromRow($row);
        }

        return $this->byId[$rootId];
    }

    /**
     * Whether a website on the given host uses this Search Console verification
     * token. Root pages without a domain match every host.
     */
    public function hasVerificationToken(string $host, string $token): bool
    {
        return false !== $this->connection->fetchOne(
            "SELECT id FROM tl_page WHERE type = 'root' AND sitekit_gsc_verification = ? AND (dns = ? OR dns = '')",
            [$token, $host],
        );
    }

    /**
     * @param array<string, string> $values Only keys from self::FIELDS are written
     */
    public function save(int $rootId, array $values): void
    {
        $values = array_intersect_key($values, array_flip(self::FIELDS));

        if (!$values) {
            return;
        }

        $values['tstamp'] = time();

        $this->connection->update('tl_page', $values, ['id' => $rootId, 'type' => 'root']);
        unset($this->byId[$rootId]);

        // Every page response is tagged with the IDs of its page trail, so this
        // purges all cached pages of the website (the tracking code changed).
        $this->cacheInvalidator?->invalidateTags(['contao.db.tl_page.'.$rootId]);
    }
}
