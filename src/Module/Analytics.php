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
 * Google Analytics 4 (Admin API for setup, Data API for reports).
 */
class Analytics
{
    public const SCOPE = 'https://www.googleapis.com/auth/analytics.readonly';

    public const METRICS = ['activeUsers', 'sessions', 'screenPageViews', 'engagementRate', 'averageSessionDuration'];

    private const ADMIN_URL = 'https://analyticsadmin.googleapis.com/v1beta';
    private const DATA_URL = 'https://analyticsdata.googleapis.com/v1beta';

    public function __construct(
        private readonly GoogleClient $client,
        private readonly ReportCache $cache,
    ) {
    }

    /**
     * GA4 properties of all accounts the connected user can access.
     *
     * @return list<array{id: string, name: string, account: string}>
     */
    public function listProperties(bool $refresh = false): array
    {
        return $this->cache->get('ga4.properties', function (): array {
            $properties = [];
            $pageToken = null;

            do {
                $query = ['pageSize' => 200];

                if (null !== $pageToken) {
                    $query['pageToken'] = $pageToken;
                }

                $data = $this->client->request('GET', self::ADMIN_URL.'/accountSummaries', ['query' => $query]);

                foreach ($data['accountSummaries'] ?? [] as $account) {
                    foreach ($account['propertySummaries'] ?? [] as $property) {
                        $properties[] = [
                            'id' => self::stripPrefix((string) $property['property'], 'properties/'),
                            'name' => (string) ($property['displayName'] ?? ''),
                            'account' => (string) ($account['displayName'] ?? ''),
                        ];
                    }
                }

                $pageToken = $data['nextPageToken'] ?? null;
            } while (null !== $pageToken && '' !== $pageToken);

            return $properties;
        }, $refresh);
    }

    /**
     * Web data streams of a property with their measurement IDs.
     *
     * @return list<array{measurementId: string, uri: string, name: string}>
     */
    public function listWebStreams(string $propertyId, bool $refresh = false): array
    {
        self::assertPropertyId($propertyId);

        return $this->cache->get('ga4.streams.'.$propertyId, function () use ($propertyId): array {
            $data = $this->client->request('GET', self::ADMIN_URL.'/properties/'.$propertyId.'/dataStreams', ['query' => ['pageSize' => 200]]);
            $streams = [];

            foreach ($data['dataStreams'] ?? [] as $stream) {
                if (!isset($stream['webStreamData']['measurementId'])) {
                    continue;
                }

                $streams[] = [
                    'measurementId' => (string) $stream['webStreamData']['measurementId'],
                    'uri' => (string) ($stream['webStreamData']['defaultUri'] ?? ''),
                    'name' => (string) ($stream['displayName'] ?? ''),
                ];
            }

            return $streams;
        }, $refresh);
    }

    /**
     * Picks the web stream whose URL matches the domain, or the only stream.
     */
    public function findMeasurementId(string $propertyId, string $domain): string|null
    {
        $streams = $this->listWebStreams($propertyId);
        $domain = strtolower(preg_replace('/^www\./', '', $domain) ?? $domain);

        foreach ($streams as $stream) {
            $host = strtolower((string) parse_url($stream['uri'], PHP_URL_HOST));

            if ('' !== $domain && preg_replace('/^www\./', '', $host) === $domain) {
                return $stream['measurementId'];
            }
        }

        return 1 === \count($streams) ? $streams[0]['measurementId'] : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOverview(string $propertyId, int $days, bool $refresh = false): array
    {
        self::assertPropertyId($propertyId);

        $range = new DateRange($days, 1);

        return $this->cache->get('ga4.overview.'.$propertyId.'.'.$range->getKey(), function () use ($propertyId, $range): array {
            $dateRanges = [
                ['startDate' => $range->start->format('Y-m-d'), 'endDate' => $range->end->format('Y-m-d')],
                ['startDate' => $range->previousStart->format('Y-m-d'), 'endDate' => $range->previousEnd->format('Y-m-d')],
            ];

            $metrics = array_map(static fn (string $name): array => ['name' => $name], self::METRICS);

            // With two date ranges, the API adds a "dateRange" dimension to each row
            $totals = $this->runReport($propertyId, ['dateRanges' => $dateRanges, 'metrics' => $metrics]);
            $current = $previous = array_fill_keys(self::METRICS, 0.0);

            foreach ($totals['rows'] ?? [] as $row) {
                $values = $this->metricValues($row);

                if ('date_range_1' === ($row['dimensionValues'][0]['value'] ?? '')) {
                    $previous = $values;
                } else {
                    $current = $values;
                }
            }

            $daily = array_fill_keys($range->getDates(), 0);
            $dailyReport = $this->runReport($propertyId, [
                'dateRanges' => [$dateRanges[0]],
                'dimensions' => [['name' => 'date']],
                'metrics' => [['name' => 'activeUsers']],
                'orderBys' => [['dimension' => ['dimensionName' => 'date']]],
                'limit' => 400,
            ]);

            foreach ($dailyReport['rows'] ?? [] as $row) {
                $date = \DateTimeImmutable::createFromFormat('Ymd', (string) $row['dimensionValues'][0]['value']);

                if ($date) {
                    $daily[$date->format('Y-m-d')] = (int) $row['metricValues'][0]['value'];
                }
            }

            return [
                'start' => $range->start->format('Y-m-d'),
                'end' => $range->end->format('Y-m-d'),
                'totals' => $current,
                'previous' => $previous,
                'daily' => $daily,
                'pages' => $this->topList($propertyId, $dateRanges[0], 'pagePath', ['screenPageViews', 'activeUsers']),
                'channels' => $this->topList($propertyId, $dateRanges[0], 'sessionDefaultChannelGroup', ['sessions', 'activeUsers']),
            ];
        }, $refresh);
    }

    /**
     * @param array<string, string> $dateRange
     * @param list<string>          $metricNames
     *
     * @return list<array<string, string|float>>
     */
    private function topList(string $propertyId, array $dateRange, string $dimension, array $metricNames): array
    {
        $report = $this->runReport($propertyId, [
            'dateRanges' => [$dateRange],
            'dimensions' => [['name' => $dimension]],
            'metrics' => array_map(static fn (string $name): array => ['name' => $name], $metricNames),
            'orderBys' => [['metric' => ['metricName' => $metricNames[0]], 'desc' => true]],
            'limit' => 10,
        ]);

        $rows = [];

        foreach ($report['rows'] ?? [] as $row) {
            $item = ['key' => (string) $row['dimensionValues'][0]['value']];

            foreach ($metricNames as $i => $name) {
                $item[$name] = (float) ($row['metricValues'][$i]['value'] ?? 0);
            }

            $rows[] = $item;
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, float>
     */
    private function metricValues(array $row): array
    {
        $values = [];

        foreach (self::METRICS as $i => $name) {
            $values[$name] = (float) ($row['metricValues'][$i]['value'] ?? 0);
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<mixed>
     */
    private function runReport(string $propertyId, array $body): array
    {
        return $this->client->request('POST', self::DATA_URL.'/properties/'.$propertyId.':runReport', ['json' => $body]);
    }

    private static function stripPrefix(string $value, string $prefix): string
    {
        return str_starts_with($value, $prefix) ? substr($value, \strlen($prefix)) : $value;
    }

    private static function assertPropertyId(string $propertyId): void
    {
        if (!preg_match('/^\d+$/', $propertyId)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a valid GA4 property ID.', $propertyId));
        }
    }
}
