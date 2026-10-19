<?php

declare(strict_types=1);

/*
 * Google Site Kit bundle for Contao Open Source CMS
 *
 * Copyright (C) pdir GmbH <develop@pdir.de>
 */

namespace Pdir\SiteKitBundle\Module;

use Pdir\SiteKitBundle\Google\Exception\GoogleApiException;
use Pdir\SiteKitBundle\Google\GoogleClient;
use Pdir\SiteKitBundle\Site\SiteConfig;
use Pdir\SiteKitBundle\Site\SiteRepository;

/**
 * Verifies the ownership of a website with the Site Verification API (HTML file
 * method, the file is served by the SearchConsoleVerificationController) and
 * adds it as a property to Search Console.
 */
class SiteVerification
{
    /**
     * Requested only when a site is verified (incremental authorization), so the
     * regular connection stays read-only.
     */
    public const SCOPES = [
        'https://www.googleapis.com/auth/siteverification.verify_only',
        'https://www.googleapis.com/auth/webmasters',
    ];

    private const API_URL = 'https://www.googleapis.com/siteVerification/v1';

    public function __construct(
        private readonly GoogleClient $client,
        private readonly SiteRepository $sites,
        private readonly SearchConsole $searchConsole,
    ) {
    }

    public function hasScopes(): bool
    {
        foreach (self::SCOPES as $scope) {
            if (!$this->client->hasScope($scope)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return string The verified property URL
     */
    public function verify(SiteConfig $site): string
    {
        $siteUrl = $site->getRootUrl();

        if (null === $siteUrl) {
            throw new \InvalidArgumentException('The website has no domain.');
        }

        $resource = ['type' => 'SITE', 'identifier' => $siteUrl];

        $data = $this->client->request('POST', self::API_URL.'/token', [
            'json' => ['site' => $resource, 'verificationMethod' => 'FILE'],
        ]);

        // For the FILE method, the token is the file name (google….html)
        $token = SiteConfig::normalizeVerificationToken((string) ($data['token'] ?? ''));

        if (null === $token || '' === $token) {
            throw new GoogleApiException('Google did not return a verification token.');
        }

        // The file has to be served before Google checks it
        $this->sites->save($site->rootId, ['sitekit_gsc_verification' => $token]);

        $this->client->request('POST', self::API_URL.'/webResource', [
            'query' => ['verificationMethod' => 'FILE'],
            'json' => ['site' => $resource],
        ]);

        $this->searchConsole->addProperty($siteUrl);

        if (!$site->hasSearchConsole()) {
            $this->sites->save($site->rootId, ['sitekit_gsc_property' => $siteUrl]);
        }

        // Reload the property list, so the new property can be selected
        try {
            $this->searchConsole->listProperties(true);
        } catch (GoogleApiException) {
            // The list is loaded again on the next page view
        }

        return $siteUrl;
    }
}
