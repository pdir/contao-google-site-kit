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

use Pdir\SiteKitBundle\Dashboard\Presenter;
use Pdir\SiteKitBundle\Google\GoogleClient;
use Pdir\SiteKitBundle\Google\ReportCache;

/**
 * PageSpeed Insights: Lighthouse lab data and Core Web Vitals field data (CrUX).
 * Works without a Google connection; an API key raises the quota.
 */
class PageSpeed
{
    public const STRATEGIES = ['mobile', 'desktop'];

    private const API_URL = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';
    private const CACHE_TTL = 21600;

    private const LAB_AUDITS = [
        'first-contentful-paint' => 'FCP',
        'largest-contentful-paint' => 'LCP',
        'total-blocking-time' => 'TBT',
        'cumulative-layout-shift' => 'CLS',
        'speed-index' => 'SI',
    ];

    private const FIELD_METRICS = [
        'LARGEST_CONTENTFUL_PAINT_MS' => 'LCP',
        'INTERACTION_TO_NEXT_PAINT' => 'INP',
        'CUMULATIVE_LAYOUT_SHIFT_SCORE' => 'CLS',
        'FIRST_CONTENTFUL_PAINT_MS' => 'FCP',
        'EXPERIMENTAL_TIME_TO_FIRST_BYTE' => 'TTFB',
    ];

    public function __construct(
        private readonly GoogleClient $client,
        private readonly ReportCache $cache,
        private readonly Presenter $presenter,
        private readonly string|null $pageSpeedApiKey = null,
    ) {
    }

    public function hasApiKey(): bool
    {
        return '' !== (string) $this->pageSpeedApiKey;
    }

    /**
     * @return array<string, mixed>
     */
    public function analyze(string $url, string $strategy, string $locale = 'en', bool $refresh = false): array
    {
        if (!\in_array($strategy, self::STRATEGIES, true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid strategy "%s".', $strategy));
        }

        if (!preg_match('#^https?://#i', $url)) {
            throw new \InvalidArgumentException('Only absolute HTTP(S) URLs can be analyzed.');
        }

        return $this->cache->get('psi.'.$strategy.'.'.$locale.'.'.$url, function () use ($url, $strategy, $locale): array {
            $query = ['url' => $url, 'strategy' => $strategy, 'category' => 'PERFORMANCE', 'locale' => $locale];

            if ($this->hasApiKey()) {
                $query['key'] = $this->pageSpeedApiKey;
            }

            $data = $this->client->publicRequest('GET', self::API_URL, ['query' => $query, 'timeout' => 90]);

            $this->presenter->setLocale($locale);

            return $this->transform($data, $url, $strategy);
        }, $refresh, self::CACHE_TTL);
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<string, mixed>
     */
    public function transform(array $data, string $url, string $strategy): array
    {
        $lighthouse = $data['lighthouseResult'] ?? [];
        $audits = $lighthouse['audits'] ?? [];
        $score = $lighthouse['categories']['performance']['score'] ?? null;

        $lab = [];

        foreach (self::LAB_AUDITS as $id => $label) {
            if (!isset($audits[$id])) {
                continue;
            }

            $lab[] = [
                'id' => $label,
                'title' => (string) ($audits[$id]['title'] ?? $label),
                'value' => (string) ($audits[$id]['displayValue'] ?? ''),
                'rating' => self::ratingFromScore($audits[$id]['score'] ?? null),
            ];
        }

        $field = [];
        $experience = $data['loadingExperience'] ?? [];

        foreach (self::FIELD_METRICS as $key => $label) {
            if (!isset($experience['metrics'][$key])) {
                continue;
            }

            $metric = $experience['metrics'][$key];
            $percentile = (float) ($metric['percentile'] ?? 0);

            $field[] = [
                'id' => $label,
                // CLS is reported multiplied by 100
                'value' => 'CLS' === $label ? $this->presenter->number($percentile / 100, 2) : $this->presenter->number($percentile / 1000, 1).' s',
                'rating' => self::ratingFromCategory((string) ($metric['category'] ?? '')),
            ];
        }

        $opportunities = [];

        foreach ($audits as $audit) {
            $savings = (float) ($audit['details']['overallSavingsMs'] ?? 0);

            if ('opportunity' !== ($audit['details']['type'] ?? '') || $savings < 100) {
                continue;
            }

            $opportunities[] = [
                'title' => (string) ($audit['title'] ?? ''),
                'savings' => $savings,
                'value' => (string) ($audit['displayValue'] ?? ''),
            ];
        }

        usort($opportunities, static fn (array $a, array $b): int => $b['savings'] <=> $a['savings']);

        return [
            'url' => $url,
            'strategy' => $strategy,
            'score' => null === $score ? null : (int) round((float) $score * 100),
            'rating' => self::ratingFromScore($score),
            'lab' => $lab,
            'field' => $field,
            'fieldOverall' => isset($experience['overall_category']) ? self::ratingFromCategory((string) $experience['overall_category']) : null,
            'fieldIsOrigin' => (bool) ($experience['origin_fallback'] ?? false),
            'opportunities' => \array_slice($opportunities, 0, 5),
            'fetchedAt' => (string) ($lighthouse['fetchTime'] ?? $data['analysisUTCTimestamp'] ?? ''),
        ];
    }

    private static function ratingFromScore(mixed $score): string
    {
        if (!is_numeric($score)) {
            return 'none';
        }

        return match (true) {
            $score >= 0.9 => 'good',
            $score >= 0.5 => 'average',
            default => 'poor',
        };
    }

    private static function ratingFromCategory(string $category): string
    {
        return match ($category) {
            'FAST' => 'good',
            'AVERAGE' => 'average',
            'SLOW' => 'poor',
            default => 'none',
        };
    }
}
