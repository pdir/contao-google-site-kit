<?php

declare(strict_types=1);

/*
 * Google Site Kit bundle for Contao Open Source CMS
 *
 * Copyright (C) pdir GmbH <develop@pdir.de>
 */

namespace Pdir\SiteKitBundle\Controller\Backend;

use Pdir\SiteKitBundle\Google\CredentialProvider;
use Pdir\SiteKitBundle\Google\Exception\GoogleApiException;
use Pdir\SiteKitBundle\Google\GoogleClient;
use Pdir\SiteKitBundle\Module\SiteVerification;
use Pdir\SiteKitBundle\Site\SiteRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Connects Site Kit to a Google account (OAuth 2.0 authorization code flow).
 */
class OAuthController extends AbstractSiteKitController
{
    private const SESSION_STATE = 'pdir_site_kit_oauth_state';

    /**
     * Root page ID of a website to verify once the additional scopes are granted.
     */
    private const SESSION_VERIFY_SITE = 'pdir_site_kit_oauth_verify_site';

    public function __construct(
        private readonly GoogleClient $client,
        private readonly CredentialProvider $credentials,
        private readonly SiteRepository $sites,
        private readonly SiteVerification $siteVerification,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * POST, so that the request token protects against CSRF.
     */
    #[Route(
        '%contao.backend.route_prefix%/site-kit/connect',
        name: 'pdir_site_kit_connect',
        defaults: ['_scope' => 'backend', '_store_referrer' => false],
        methods: ['POST'],
    )]
    public function connect(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->credentials->hasCredentials()) {
            $this->flash('error', $this->translator->trans('oauth.no_credentials', [], 'pdir_site_kit'));

            return $this->redirectToSettings();
        }

        $state = bin2hex(random_bytes(24));
        $session = $request->getSession();
        $session->set(self::SESSION_STATE, $state);

        // "Verify automatically" asks for the verification scopes on top
        $verifySite = $request->request->getInt('verify_site');
        $extraScopes = [];

        if ($verifySite > 0) {
            $session->set(self::SESSION_VERIFY_SITE, $verifySite);
            $extraScopes = SiteVerification::SCOPES;
        } else {
            $session->remove(self::SESSION_VERIFY_SITE);
        }

        $url = $this->client->getAuthorizationUrl($this->getRedirectUri(), $state, $this->client->getAccountEmail(), $extraScopes);

        return new RedirectResponse($url, Response::HTTP_SEE_OTHER);
    }

    #[Route(
        '%contao.backend.route_prefix%/site-kit/oauth',
        name: 'pdir_site_kit_oauth_callback',
        defaults: ['_scope' => 'backend', '_store_referrer' => false],
        methods: ['GET'],
    )]
    public function callback(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $session = $request->getSession();
        $expectedState = (string) $session->get(self::SESSION_STATE, '');
        $verifySite = (int) $session->get(self::SESSION_VERIFY_SITE, 0);
        $session->remove(self::SESSION_STATE);
        $session->remove(self::SESSION_VERIFY_SITE);

        if ('' === $expectedState || !hash_equals($expectedState, $request->query->getString('state'))) {
            $this->flash('error', $this->translator->trans('oauth.invalid_state', [], 'pdir_site_kit'));

            return $this->redirectToSettings();
        }

        if ($request->query->has('error')) {
            $this->flash('error', $this->translator->trans('oauth.denied', ['%error%' => $request->query->getString('error')], 'pdir_site_kit'));

            return $this->redirectToSettings();
        }

        try {
            $this->client->connect($request->query->getString('code'), $this->getRedirectUri());
        } catch (GoogleApiException $e) {
            $this->flash('error', $this->translator->trans('oauth.failed', ['%error%' => $e->getMessage()], 'pdir_site_kit'));

            return $this->redirectToSettings();
        }

        $missing = array_diff(GoogleClient::SCOPES, $this->client->getGrantedScopes(), ['openid', 'email']);

        if ($missing) {
            $this->flash('error', $this->translator->trans('oauth.missing_scopes', [], 'pdir_site_kit'));
        } else {
            $this->flash('confirm', $this->translator->trans('oauth.connected', ['%email%' => (string) $this->client->getAccountEmail()], 'pdir_site_kit'));
        }

        $site = $verifySite > 0 ? $this->sites->find($verifySite) : null;

        if (null !== $site) {
            if ($this->siteVerification->hasScopes()) {
                $this->verifySite($this->siteVerification, $site, $this->translator);
            } else {
                $this->flash('error', $site->title.': '.$this->translator->trans('verification.missing_scopes', [], 'pdir_site_kit'));
            }

            return $this->redirectToSettings($site->rootId);
        }

        return $this->redirectToSettings();
    }

    #[Route(
        '%contao.backend.route_prefix%/site-kit/disconnect',
        name: 'pdir_site_kit_disconnect',
        defaults: ['_scope' => 'backend', '_store_referrer' => false],
        methods: ['POST'],
    )]
    public function disconnect(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $this->client->disconnect();
        $this->flash('confirm', $this->translator->trans('oauth.disconnected', [], 'pdir_site_kit'));

        return $this->redirectToSettings();
    }

    private function getRedirectUri(): string
    {
        return $this->generateUrl('pdir_site_kit_oauth_callback', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function redirectToSettings(int|null $siteId = null): RedirectResponse
    {
        $url = $this->generateUrl('pdir_site_kit_settings').(null !== $siteId ? '#site-'.$siteId : '');

        return new RedirectResponse($url, Response::HTTP_SEE_OTHER);
    }
}
