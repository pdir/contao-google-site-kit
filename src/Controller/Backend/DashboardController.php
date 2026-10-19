<?php

declare(strict_types=1);

/*
 * Google Site Kit bundle for Contao Open Source CMS
 *
 * Copyright (C) pdir GmbH <develop@pdir.de>
 */

namespace Pdir\SiteKitBundle\Controller\Backend;

use Pdir\SiteKitBundle\Dashboard\DashboardBuilder;
use Pdir\SiteKitBundle\Dashboard\DateRange;
use Pdir\SiteKitBundle\Google\CredentialProvider;
use Pdir\SiteKitBundle\Google\GoogleClient;
use Pdir\SiteKitBundle\Site\SiteConfig;
use Pdir\SiteKitBundle\Site\SiteRepository;
use Pdir\SiteKitBundle\Tracking\SnippetRenderer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(
    '%contao.backend.route_prefix%/site-kit',
    name: 'pdir_site_kit_dashboard',
    defaults: ['_scope' => 'backend'],
    methods: ['GET'],
)]
class DashboardController extends AbstractSiteKitController
{
    public function __construct(
        private readonly SiteRepository $sites,
        private readonly GoogleClient $client,
        private readonly CredentialProvider $credentials,
        private readonly DashboardBuilder $builder,
        private readonly SnippetRenderer $snippets,
        private readonly TranslatorInterface $translator,
        private readonly bool $adminsOnly = true,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $this->denyAccessUnlessGranted($this->adminsOnly ? 'ROLE_ADMIN' : 'ROLE_USER');

        $GLOBALS['TL_CSS'][] = 'bundles/pdirsitekit/sitekit.css';
        $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/pdirsitekit/sitekit.js';

        $allSites = $this->sites->findAll();
        $site = $this->selectSite($allSites, $request->query->getInt('site'));
        $days = DateRange::normalizeDays($request->query->get('days'));
        $isConnected = $this->client->isConnected();
        $reports = null;

        if (null !== $site && $isConnected) {
            $reports = $this->builder->build($site, $days, $request->getLocale(), $request->query->getBoolean('refresh'));
        }

        return $this->render('@PdirSiteKit/dashboard.html.twig', [
            'headline' => htmlspecialchars($this->translator->trans('headline', [], 'pdir_site_kit')),
            'title' => $this->translator->trans('headline', [], 'pdir_site_kit'),
            'flashes' => $this->consumeFlashes(),
            'is_admin' => $this->isGranted('ROLE_ADMIN'),
            'has_credentials' => $this->credentials->hasCredentials(),
            'is_connected' => $isConnected,
            'account_email' => $this->client->getAccountEmail(),
            'sites' => $allSites,
            'site' => $site,
            'days' => $days,
            'allowed_days' => DateRange::ALLOWED_DAYS,
            'reports' => $reports,
            'tracking' => null === $site ? null : [
                'active' => $this->snippets->isActive($site),
                'measurement_id' => $this->snippets->getMeasurementId($site),
                'container_id' => $this->snippets->getContainerId($site),
                'consent' => $site->consentDefault,
            ],
            'pagespeed_url' => $site?->getStartUrl() ?? $request->getSchemeAndHttpHost().'/',
        ]);
    }

    /**
     * @param list<SiteConfig> $sites
     */
    private function selectSite(array $sites, int $id): SiteConfig|null
    {
        foreach ($sites as $site) {
            if ($site->rootId === $id) {
                return $site;
            }
        }

        // Prefer the first website that is configured for Site Kit
        foreach ($sites as $site) {
            if ($site->hasSearchConsole() || $site->hasAnalytics()) {
                return $site;
            }
        }

        return $sites[0] ?? null;
    }
}
