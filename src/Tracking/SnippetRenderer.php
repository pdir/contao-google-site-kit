<?php

declare(strict_types=1);

/*
 * This file is part of pdir/contao-site-kit.
 *
 * (c) pdir
 *
 * @license LGPL-3.0-or-later
 */

namespace Pdir\SiteKitBundle\Tracking;

use Pdir\SiteKitBundle\Site\SiteConfig;

/**
 * Builds the Google tag (gtag.js) and Google Tag Manager snippets including
 * the Consent Mode v2 defaults.
 */
class SnippetRenderer
{
    public const MEASUREMENT_ID_PATTERN = '/^G-[A-Z0-9]{4,}$/';
    public const CONTAINER_ID_PATTERN = '/^GTM-[A-Z0-9]{4,}$/';

    /**
     * Hosts that need to be allowed when a Content Security Policy is active.
     */
    public const CSP_SOURCES = [
        'script-src' => 'https://www.googletagmanager.com https://*.googletagmanager.com',
        'connect-src' => 'https://*.google-analytics.com https://*.analytics.google.com https://*.googletagmanager.com',
        'img-src' => 'https://*.google-analytics.com https://*.googletagmanager.com',
        'frame-src' => 'https://www.googletagmanager.com',
    ];

    public function getMeasurementId(SiteConfig $site): string|null
    {
        return preg_match(self::MEASUREMENT_ID_PATTERN, $site->measurementId) ? $site->measurementId : null;
    }

    public function getContainerId(SiteConfig $site): string|null
    {
        return preg_match(self::CONTAINER_ID_PATTERN, $site->tagManagerContainer) ? $site->tagManagerContainer : null;
    }

    public function isActive(SiteConfig $site): bool
    {
        return $site->trackingEnabled && (null !== $this->getMeasurementId($site) || null !== $this->getContainerId($site));
    }

    /**
     * Code for the <head> section.
     */
    public function renderHead(SiteConfig $site, string|null $nonce = null): string
    {
        if (!$this->isActive($site)) {
            return '';
        }

        $nonceAttr = null !== $nonce ? ' nonce="'.htmlspecialchars($nonce, ENT_QUOTES).'"' : '';
        $measurementId = $this->getMeasurementId($site);
        $containerId = $this->getContainerId($site);

        $state = 'granted' === $site->consentDefault ? 'granted' : 'denied';
        $consent = [
            'ad_storage' => $state,
            'ad_user_data' => $state,
            'ad_personalization' => $state,
            'analytics_storage' => $state,
        ];

        if ('denied' === $state) {
            // Give the cookie banner time to send its consent update
            $consent['wait_for_update'] = 500;
        }

        $js = 'window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}'
            .'gtag("consent","default",'.json_encode($consent, JSON_THROW_ON_ERROR).');';

        if (null !== $measurementId) {
            $js .= 'gtag("js",new Date());gtag("config",'.json_encode($measurementId, JSON_THROW_ON_ERROR).');';
        }

        $html = "<!-- Google Site Kit for Contao -->\n<script$nonceAttr>$js</script>\n";

        if (null !== $measurementId) {
            $html .= '<script async src="https://www.googletagmanager.com/gtag/js?id='.rawurlencode($measurementId).'"'.$nonceAttr."></script>\n";
        }

        if (null !== $containerId) {
            $html .= "<script$nonceAttr>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});"
                ."var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;"
                ."j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;var n=d.querySelector('script[nonce]');"
                ."if(n){j.setAttribute('nonce',n.nonce||n.getAttribute('nonce'));}f.parentNode.insertBefore(j,f);"
                ."})(window,document,'script','dataLayer',".json_encode($containerId, JSON_THROW_ON_ERROR).");</script>\n";
        }

        return $html."<!-- End Google Site Kit for Contao -->\n";
    }

    /**
     * Code directly after the opening <body> tag (Tag Manager fallback).
     */
    public function renderBody(SiteConfig $site): string
    {
        $containerId = $this->getContainerId($site);

        if (null === $containerId || !$site->trackingEnabled) {
            return '';
        }

        return '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id='.rawurlencode($containerId).'" height="0" width="0" hidden></iframe></noscript>'."\n";
    }
}
