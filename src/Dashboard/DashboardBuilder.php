<?php

declare(strict_types=1);

/*
 * Google Site Kit bundle for Contao Open Source CMS
 *
 * Copyright (C) pdir GmbH <develop@pdir.de>
 */

namespace Pdir\SiteKitBundle\Dashboard;

use Pdir\SiteKitBundle\Google\Exception\GoogleApiException;
use Pdir\SiteKitBundle\Google\Exception\NotConnectedException;
use Pdir\SiteKitBundle\Google\GoogleClient;
use Pdir\SiteKitBundle\Module\Analytics;
use Pdir\SiteKitBundle\Module\SearchConsole;
use Pdir\SiteKitBundle\Site\SiteConfig;
use Psr\Log\LoggerInterface;

/**
 * Loads the reports of one website and prepares them for the dashboard template.
 * Every card is built independently, so one failing API does not break the page.
 */
class DashboardBuilder
{
    public function __construct(
        private readonly GoogleClient $client,
        private readonly SearchConsole $searchConsole,
        private readonly Analytics $analytics,
        private readonly Presenter $presenter,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(SiteConfig $site, int $days, string $locale, bool $refresh = false): array
    {
        $this->presenter->setLocale($locale);

        return [
            'searchConsole' => $this->buildSearchConsole($site, $days, $refresh),
            'analytics' => $this->buildAnalytics($site, $days, $refresh),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSearchConsole(SiteConfig $site, int $days, bool $refresh): array
    {
        if (!$site->hasSearchConsole()) {
            return ['state' => 'unconfigured'];
        }

        if (!$this->client->hasScope(SearchConsole::SCOPE)) {
            return ['state' => 'missing_scope'];
        }

        try {
            $data = $this->searchConsole->getOverview($site->searchConsoleProperty, $days, $refresh);
        } catch (GoogleApiException $e) {
            return $this->errorState($e, 'Search Console');
        }

        $p = $this->presenter;
        $cur = $data['totals'];
        $prev = $data['previous'];

        return [
            'state' => 'ok',
            'property' => $site->searchConsoleProperty,
            'start' => $data['start'],
            'end' => $data['end'],
            'kpis' => [
                ['key' => 'clicks', 'value' => $p->compact($cur['clicks']), 'change' => $p->change($cur['clicks'], $prev['clicks'])],
                ['key' => 'impressions', 'value' => $p->compact($cur['impressions']), 'change' => $p->change($cur['impressions'], $prev['impressions'])],
                ['key' => 'ctr', 'value' => $p->percent($cur['ctr']), 'change' => $p->change($cur['ctr'], $prev['ctr'])],
                ['key' => 'position', 'value' => $p->number($cur['position'], 1), 'change' => $p->change($cur['position'], $prev['position'], true)],
            ],
            'chart' => $p->sparkline(array_column($data['daily'], 'clicks')),
            'chartMax' => $p->number(max(array_column($data['daily'], 'clicks') ?: [0])),
            'queries' => array_map(fn (array $row): array => [
                'label' => $row['key'],
                'clicks' => $p->number($row['clicks']),
                'impressions' => $p->number($row['impressions']),
                'position' => $p->number($row['position'], 1),
            ], $data['queries']),
            'pages' => array_map(fn (array $row): array => [
                'label' => $this->shortenUrl($row['key']),
                'url' => $row['key'],
                'clicks' => $p->number($row['clicks']),
                'impressions' => $p->number($row['impressions']),
                'position' => $p->number($row['position'], 1),
            ], $data['pages']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAnalytics(SiteConfig $site, int $days, bool $refresh): array
    {
        if (!$site->hasAnalytics()) {
            return ['state' => 'unconfigured'];
        }

        if (!$this->client->hasScope(Analytics::SCOPE)) {
            return ['state' => 'missing_scope'];
        }

        try {
            $data = $this->analytics->getOverview($site->analyticsProperty, $days, $refresh);
        } catch (GoogleApiException|\InvalidArgumentException $e) {
            return $this->errorState($e, 'Analytics');
        }

        $p = $this->presenter;
        $cur = $data['totals'];
        $prev = $data['previous'];

        return [
            'state' => 'ok',
            'property' => $site->analyticsProperty,
            'start' => $data['start'],
            'end' => $data['end'],
            'kpis' => [
                ['key' => 'activeUsers', 'value' => $p->compact($cur['activeUsers']), 'change' => $p->change($cur['activeUsers'], $prev['activeUsers'])],
                ['key' => 'sessions', 'value' => $p->compact($cur['sessions']), 'change' => $p->change($cur['sessions'], $prev['sessions'])],
                ['key' => 'screenPageViews', 'value' => $p->compact($cur['screenPageViews']), 'change' => $p->change($cur['screenPageViews'], $prev['screenPageViews'])],
                ['key' => 'engagementRate', 'value' => $p->percent($cur['engagementRate']), 'change' => $p->change($cur['engagementRate'], $prev['engagementRate'])],
                ['key' => 'averageSessionDuration', 'value' => $p->duration($cur['averageSessionDuration']), 'change' => $p->change($cur['averageSessionDuration'], $prev['averageSessionDuration'])],
            ],
            'chart' => $p->sparkline($data['daily']),
            'chartMax' => $p->number(max($data['daily'] ?: [0])),
            'pages' => array_map(static fn (array $row): array => [
                'label' => (string) $row['key'],
                'views' => $p->number((float) $row['screenPageViews']),
                'users' => $p->number((float) $row['activeUsers']),
            ], $data['pages']),
            'channels' => array_map(static fn (array $row): array => [
                'label' => (string) $row['key'],
                'sessions' => $p->number((float) $row['sessions']),
                'users' => $p->number((float) $row['activeUsers']),
            ], $data['channels']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function errorState(\Throwable $e, string $module): array
    {
        $this->logger?->warning(\sprintf('Site Kit: %s request failed: %s', $module, $e->getMessage()));

        return [
            'state' => $e instanceof NotConnectedException ? 'not_connected' : ($e instanceof GoogleApiException && $e->isPermissionError() ? 'permission' : 'error'),
            'error' => $e->getMessage(),
        ];
    }

    private function shortenUrl(string $url): string
    {
        $parts = parse_url($url);

        if (!\is_array($parts) || !isset($parts['host'])) {
            return $url;
        }

        $path = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');

        return rawurldecode($path);
    }
}
