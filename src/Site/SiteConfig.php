<?php

declare(strict_types=1);

/*
 * Google Site Kit bundle for Contao Open Source CMS
 *
 * Copyright (C) pdir GmbH <develop@pdir.de>
 */

namespace Pdir\SiteKitBundle\Site;

/**
 * Site Kit settings of one website root page.
 */
final class SiteConfig
{
    /**
     * Token of a Search Console HTML file verification, e.g. google8bfe7b6619fd8ee1.
     */
    public const VERIFICATION_TOKEN_PATTERN = 'google[a-z0-9]{8,64}';

    public function __construct(
        public readonly int $rootId,
        public readonly string $title,
        public readonly string $dns,
        public readonly bool $useSsl,
        public readonly string $urlPrefix,
        public readonly string $language,
        public readonly string $searchConsoleProperty,
        public readonly string $searchConsoleVerification,
        public readonly string $analyticsProperty,
        public readonly string $measurementId,
        public readonly string $tagManagerContainer,
        public readonly bool $trackingEnabled,
        public readonly string $consentDefault,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['title'],
            (string) ($row['dns'] ?? ''),
            (bool) ($row['useSSL'] ?? true),
            (string) ($row['urlPrefix'] ?? ''),
            (string) ($row['language'] ?? ''),
            (string) ($row['sitekit_gsc_property'] ?? ''),
            (string) ($row['sitekit_gsc_verification'] ?? ''),
            (string) ($row['sitekit_ga4_property'] ?? ''),
            (string) ($row['sitekit_ga4_measurement'] ?? ''),
            (string) ($row['sitekit_gtm_container'] ?? ''),
            (bool) ($row['sitekit_tracking'] ?? false),
            'granted' === ($row['sitekit_consent_default'] ?? '') ? 'granted' : 'denied',
        );
    }

    /**
     * Absolute URL of the website's start page, or null if the root page has no domain.
     */
    public function getStartUrl(): string|null
    {
        if ('' === $this->dns) {
            return null;
        }

        $url = ($this->useSsl ? 'https://' : 'http://').$this->dns.'/';

        if ('' !== $this->urlPrefix) {
            $url .= trim($this->urlPrefix, '/').'/';
        }

        return $url;
    }

    /**
     * Extracts the token from the token itself, the file name or the file content
     * Google shows ("google-site-verification: google….html"). Returns null if the
     * input contains no valid token and an empty string for an empty input.
     */
    public static function normalizeVerificationToken(string $input): string|null
    {
        $input = trim($input);

        if ('' === $input) {
            return '';
        }

        if (!preg_match('/(?:^|[\\s:\/])('.self::VERIFICATION_TOKEN_PATTERN.')(?:\.html)?$/i', $input, $matches)) {
            return null;
        }

        return $matches[1];
    }

    /**
     * Root URL of the domain (without the URL prefix), e.g. https://www.example.com/.
     */
    public function getRootUrl(): string|null
    {
        if ('' === $this->dns) {
            return null;
        }

        return ($this->useSsl ? 'https://' : 'http://').$this->dns.'/';
    }

    /**
     * URL under which Google expects the verification file.
     */
    public function getVerificationUrl(): string|null
    {
        $rootUrl = $this->getRootUrl();

        if (null === $rootUrl || '' === $this->searchConsoleVerification) {
            return null;
        }

        return $rootUrl.$this->searchConsoleVerification.'.html';
    }

    public function getLabel(): string
    {
        $parts = array_filter([$this->dns, $this->language]);

        return $this->title.($parts ? ' ('.implode(', ', $parts).')' : '');
    }

    public function hasSearchConsole(): bool
    {
        return '' !== $this->searchConsoleProperty;
    }

    public function hasAnalytics(): bool
    {
        return '' !== $this->analyticsProperty;
    }
}
