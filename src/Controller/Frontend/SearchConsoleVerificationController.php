<?php

declare(strict_types=1);

/*
 * This file is part of pdir/contao-site-kit.
 *
 * (c) pdir
 *
 * @license LGPL-3.0-or-later
 */

namespace Pdir\SiteKitBundle\Controller\Frontend;

use Pdir\SiteKitBundle\Site\SiteConfig;
use Pdir\SiteKitBundle\Site\SiteRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Serves the HTML verification file of Google Search Console, so it does not
 * have to be uploaded to the web root. The token is stored in the root page.
 */
#[Route(
    '/{token}.html',
    name: 'pdir_site_kit_gsc_verification',
    requirements: ['token' => SiteConfig::VERIFICATION_TOKEN_PATTERN],
    methods: ['GET', 'HEAD'],
    priority: 10,
)]
class SearchConsoleVerificationController
{
    public function __construct(private readonly SiteRepository $sites)
    {
    }

    public function __invoke(Request $request, string $token): Response
    {
        if (!$this->sites->hasVerificationToken($request->getHost(), $token)) {
            throw new NotFoundHttpException();
        }

        return new Response('google-site-verification: '.$token.'.html', Response::HTTP_OK, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
