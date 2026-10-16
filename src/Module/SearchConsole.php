<?php

declare(strict_types=1);

/*
 * This file is part of pdir/contao-site-kit.
 *
 * (c) pdir
 *
 * @license LGPL-3.0-or-later
 */

namespace Pdir\SiteKitBundle\Module;

use Pdir\SiteKitBundle\Dashboard\DateRange;
use Pdir\SiteKitBundle\Google\GoogleClient;
use Pdir\SiteKitBundle\Google\ReportCache;

/**
 * Google Search Console (Search Analytics API).
 */
class SearchConsole
{
    public const SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';

    /**
     * Search Console data is usually complete after two days.
     */
    public const DATA_DELAY_DAYS = 2;

    private const BASE_URL = 'https://www.googleapis.com/webmasters/v3/sites';

    public function __construct(
        private readonly GoogleClient $client,
        private readonly ReportCache $cache,
    ) {
    }

    /**
     * Properties the connected account has access to.
     *
     * @return list<array{siteUrl: string, permissionLevel: string}>
     */
    public function listProperties(bool $refresh = false): array
    {
        return $this->cache->get('gsc.sites', function (): array {
            $data = $this->client->request('GET', self::BASE_URL);
            $sites = [];

            foreach ($data['siteEntry'] ?? [] as $entry) {
                if ('siteUnverifiedUser' === ($entry['permissionLevel'] ?? '')) {
                    continue;
                }

                $sites[] = ['siteUrl' => (string) $entry['siteUrl'], 'permissionLevel' => (string) ($entry['permissionLevel'] ?? '')];
            }

            usort($sites, static fn (array $a, array $b): int => strcmp($a['siteUrl'], $b['siteUrl']));

            return $sites;
        }, $refresh);
    }

    /**
     * Totals (current and previous period), clicks and impressions per day, top
     * queries and top pages.
     *
     * @return array<string, mixed>
     */
    /**
     * Adds a verified site to the Search Console of the connected account.
     * Requires the full webmasters scope.
     */
    public function addProperty(string $siteUrl): void
    {
        $this->client->request('PUT', self::BASE_URL.'/'.rawurlencode($siteUrl));
    }

    public function getOverview(string $property, int $days, bool $refresh = false): array
    {
        $range = new DateRange($days, self::DATA_DELAY_DAYS);

        return $this->cache->get('gsc.overview.'.$property.'.'.$range->getKey(), function () use ($property, $range): array {
            $current = $this->query($property, $range->start, $range->end);
            $previous = $this->query($property, $range->previousStart, $range->previousEnd);

            $daily = array_fill_keys($range->getDates(), ['clicks' => 0, 'impressions' => 0]);

            foreach ($this->query($property, $range->start, $range->end, ['date']) as $row) {
                $daily[$row['keys'][0]] = ['clicks' => (int) $row['clicks'], 'impressions' => (int) $row['impressions']];
            }

            return [
                'start' => $range->start->format('Y-m-d'),
                'end' => $range->end->format('Y-m-d'),
                'totals' => $this->totals($current[0] ?? []),
                'previous' => $this->totals($previous[0] ?? []),
                'daily' => $daily,
                'queries' => $this->rows($this->query($property, $range->start, $range->end, ['query'], 10)),
                'pages' => $this->rows($this->query($property, $range->start, $range->end, ['page'], 10)),
            ];
        }, $refresh);
    }

    /**
     * @param list<string> $dimensions
     *
     * @return list<array<string, mixed>>
     */
    private function query(string $property, \DateTimeImmutable $start, \DateTimeImmutable $end, array $dimensions = [], int $rowLimit = 1000): array
    {
        $body = [
            'startDate' => $start->format('Y-m-d'),
            'endDate' => $end->format('Y-m-d'),
            'rowLimit' => $rowLimit,
        ];

        if ($dimensions) {
            $body['dimensions'] = $dimensions;
        }

        $data = $this->client->request('POST', self::BASE_URL.'/'.rawurlencode($property).'/searchAnalytics/query', ['json' => $body]);

        return array_values($data['rows'] ?? []);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{clicks: int, impressions: int, ctr: float, position: float}
     */
    private function totals(array $row): array
    {
        return [
            'clicks' => (int) ($row['clicks'] ?? 0),
            'impressions' => (int) ($row['impressions'] ?? 0),
            'ctr' => (float) ($row['ctr'] ?? 0),
            'position' => (float) ($row['position'] ?? 0),
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array{key: string, clicks: int, impressions: int, ctr: float, position: float}>
     */
    private function rows(array $rows): array
    {
        return array_map(
            fn (array $row): array => ['key' => (string) ($row['keys'][0] ?? '')] + $this->totals($row),
            $rows,
        );
    }
}
