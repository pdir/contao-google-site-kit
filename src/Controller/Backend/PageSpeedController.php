<?php

declare(strict_types=1);

/*
 * Google Site Kit bundle for Contao Open Source CMS
 *
 * Copyright (C) pdir GmbH <develop@pdir.de>
 */

namespace Pdir\SiteKitBundle\Controller\Backend;

use Pdir\SiteKitBundle\Google\Exception\GoogleApiException;
use Pdir\SiteKitBundle\Module\PageSpeed;
use Pdir\SiteKitBundle\Site\SiteRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Runs PageSpeed Insights and returns the result as an HTML fragment. Called
 * asynchronously by the dashboard, because an analysis takes 10 to 30 seconds.
 */
#[Route(
    '%contao.backend.route_prefix%/site-kit/pagespeed',
    name: 'pdir_site_kit_pagespeed',
    defaults: ['_scope' => 'backend', '_store_referrer' => false],
    methods: ['GET'],
)]
class PageSpeedController extends AbstractSiteKitController
{
    public function __construct(
        private readonly PageSpeed $pageSpeed,
        private readonly SiteRepository $sites,
        private readonly bool $adminsOnly = true,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $this->denyAccessUnlessGranted($this->adminsOnly ? 'ROLE_ADMIN' : 'ROLE_USER');

        $url = trim($request->query->getString('url'));
        $strategy = $request->query->getString('strategy', 'mobile');
        $site = $this->sites->find($request->query->getInt('site'));

        // Only pages of the selected website (or of this installation) can be analyzed
        $allowedHost = null !== $site && '' !== $site->dns ? $site->dns : $request->getHost();
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if (!preg_match('#^https?://#i', $url) || $host !== strtolower($allowedHost)) {
            return $this->fragment(['error' => 'invalid_url', 'allowed_host' => $allowedHost], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $result = $this->pageSpeed->analyze($url, $strategy, substr($request->getLocale(), 0, 2), $request->query->getBoolean('refresh'));
        } catch (GoogleApiException $e) {
            if ($e->isQuotaError()) {
                return $this->fragment(['error' => 'quota', 'has_api_key' => $this->pageSpeed->hasApiKey(), 'message' => $e->getMessage()], Response::HTTP_TOO_MANY_REQUESTS);
            }

            return $this->fragment(['error' => 'api', 'message' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        } catch (\InvalidArgumentException $e) {
            return $this->fragment(['error' => 'api', 'message' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }

        return $this->fragment(['result' => $result]);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function fragment(array $parameters, int $status = Response::HTTP_OK): Response
    {
        $response = new Response($this->renderView('@PdirSiteKit/_pagespeed.html.twig', $parameters), $status);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
