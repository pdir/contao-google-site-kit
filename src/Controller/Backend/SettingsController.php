<?php

declare(strict_types=1);

/*
 * This file is part of pdir/contao-site-kit.
 *
 * (c) pdir
 *
 * @license LGPL-3.0-or-later
 */

namespace Pdir\SiteKitBundle\Controller\Backend;

use Pdir\SiteKitBundle\Google\CredentialProvider;
use Pdir\SiteKitBundle\Google\Exception\GoogleApiException;
use Pdir\SiteKitBundle\Google\GoogleClient;
use Pdir\SiteKitBundle\Google\SettingsStore;
use Pdir\SiteKitBundle\Module\Analytics;
use Pdir\SiteKitBundle\Module\SearchConsole;
use Pdir\SiteKitBundle\Module\SiteVerification;
use Pdir\SiteKitBundle\Site\SiteConfig;
use Pdir\SiteKitBundle\Site\SiteRepository;
use Pdir\SiteKitBundle\Tracking\SnippetRenderer;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Google Cloud credentials, the Google connection and the mapping of website
 * root pages to Search Console / Analytics properties. Administrators only.
 */
class SettingsController extends AbstractSiteKitController
{
    public function __construct(
        private readonly SiteRepository $sites,
        private readonly GoogleClient $client,
        private readonly CredentialProvider $credentials,
        private readonly SettingsStore $settings,
        private readonly SearchConsole $searchConsole,
        private readonly Analytics $analytics,
        private readonly SiteVerification $siteVerification,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route(
        '%contao.backend.route_prefix%/site-kit/settings',
        name: 'pdir_site_kit_settings',
        defaults: ['_scope' => 'backend'],
        methods: ['GET'],
    )]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $GLOBALS['TL_CSS'][] = 'bundles/pdirsitekit/sitekit.css';
        $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/pdirsitekit/sitekit.js';

        $refresh = $request->query->getBoolean('refresh');
        $gscProperties = $ga4Properties = [];
        $loadErrors = [];

        if ($this->client->isConnected()) {
            try {
                $gscProperties = $this->searchConsole->listProperties($refresh);
            } catch (GoogleApiException $e) {
                $loadErrors[] = 'Search Console: '.$e->getMessage();
            }

            try {
                $ga4Properties = $this->analytics->listProperties($refresh);
            } catch (GoogleApiException $e) {
                $loadErrors[] = 'Analytics: '.$e->getMessage();
            }
        }

        return $this->render('@PdirSiteKit/settings.html.twig', [
            'headline' => htmlspecialchars($this->translator->trans('headline', [], 'pdir_site_kit').' › '.$this->translator->trans('settings.title', [], 'pdir_site_kit')),
            'title' => $this->translator->trans('settings.title', [], 'pdir_site_kit'),
            'flashes' => $this->consumeFlashes(),
            'credentials_external' => $this->credentials->isConfiguredExternally(),
            'client_id' => $this->credentials->getClientId(),
            'has_secret' => null !== $this->credentials->getClientSecret(),
            'has_credentials' => $this->credentials->hasCredentials(),
            'redirect_uri' => $this->generateUrl('pdir_site_kit_oauth_callback', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'is_connected' => $this->client->isConnected(),
            'account_email' => $this->client->getAccountEmail(),
            'granted_scopes' => $this->client->getGrantedScopes(),
            'required_scopes' => GoogleClient::SCOPES,
            'sites' => $this->sites->findAll(),
            'gsc_properties' => $gscProperties,
            'ga4_properties' => $ga4Properties,
            'load_errors' => $loadErrors,
            'has_verification_scopes' => $this->siteVerification->hasScopes(),
        ]);
    }

    #[Route(
        '%contao.backend.route_prefix%/site-kit/settings/credentials',
        name: 'pdir_site_kit_settings_credentials',
        defaults: ['_scope' => 'backend'],
        methods: ['POST'],
    )]
    public function saveCredentials(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->credentials->isConfiguredExternally()) {
            return $this->backToSettings();
        }

        $clientId = trim($request->request->getString('client_id'));
        $clientSecret = trim($request->request->getString('client_secret'));

        if ('' !== $clientId && !preg_match('/^[\w.-]+\.apps\.googleusercontent\.com$/', $clientId)) {
            $this->flash('error', $this->translator->trans('settings.invalid_client_id', [], 'pdir_site_kit'));

            return $this->backToSettings();
        }

        $previousClientId = $this->settings->get(SettingsStore::CLIENT_ID);
        $this->settings->set(SettingsStore::CLIENT_ID, '' === $clientId ? null : $clientId);

        // An empty secret field keeps the stored secret (it is never shown again)
        if ('' !== $clientSecret) {
            $this->settings->setSecret(SettingsStore::CLIENT_SECRET, $clientSecret);
        } elseif ('' === $clientId) {
            $this->settings->setSecret(SettingsStore::CLIENT_SECRET, null);
        }

        // Tokens belong to the old OAuth client and cannot be refreshed with a new one
        if ($previousClientId !== $this->settings->get(SettingsStore::CLIENT_ID) && $this->client->isConnected()) {
            $this->settings->clearConnection();
        }

        $this->flash('confirm', $this->translator->trans('settings.saved', [], 'pdir_site_kit'));

        return $this->backToSettings();
    }

    #[Route(
        '%contao.backend.route_prefix%/site-kit/settings/site/{id}',
        name: 'pdir_site_kit_settings_site',
        requirements: ['id' => '\d+'],
        defaults: ['_scope' => 'backend'],
        methods: ['POST'],
    )]
    public function saveSite(Request $request, int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $site = $this->sites->find($id);

        if (null === $site) {
            throw $this->createNotFoundException();
        }

        $input = $request->request;
        $gscProperty = trim($input->getString('gsc_property'));
        $gscVerification = SiteConfig::normalizeVerificationToken($input->getString('gsc_verification'));
        $ga4Property = trim($input->getString('ga4_property'));
        $measurementId = strtoupper(trim($input->getString('ga4_measurement')));
        $containerId = strtoupper(trim($input->getString('gtm_container')));
        $errors = [];

        if ('' !== $ga4Property && !preg_match('/^\d+$/', $ga4Property)) {
            $errors[] = $this->translator->trans('settings.invalid_property', [], 'pdir_site_kit');
        }

        if ('' !== $gscProperty && !preg_match('#^(sc-domain:[a-z0-9.-]+|https?://\S+)$#i', $gscProperty)) {
            $errors[] = $this->translator->trans('settings.invalid_gsc_property', [], 'pdir_site_kit');
        }

        if (null === $gscVerification) {
            $errors[] = $this->translator->trans('settings.invalid_gsc_verification', [], 'pdir_site_kit');
        }

        // Look up the measurement ID of the selected property if none was entered
        if ('' === $measurementId && '' !== $ga4Property && !$errors && $this->client->isConnected()) {
            try {
                $measurementId = (string) $this->analytics->findMeasurementId($ga4Property, $site->dns);
            } catch (GoogleApiException) {
                // Leave it empty, the administrator can enter it manually
            }
        }

        if ('' !== $measurementId && !preg_match(SnippetRenderer::MEASUREMENT_ID_PATTERN, $measurementId)) {
            $errors[] = $this->translator->trans('settings.invalid_measurement_id', [], 'pdir_site_kit');
        }

        if ('' !== $containerId && !preg_match(SnippetRenderer::CONTAINER_ID_PATTERN, $containerId)) {
            $errors[] = $this->translator->trans('settings.invalid_container_id', [], 'pdir_site_kit');
        }

        if ($errors) {
            foreach ($errors as $error) {
                $this->flash('error', $site->title.': '.$error);
            }

            return $this->backToSettings($id);
        }

        $this->sites->save($id, [
            'sitekit_gsc_property' => $gscProperty,
            'sitekit_gsc_verification' => (string) $gscVerification,
            'sitekit_ga4_property' => $ga4Property,
            'sitekit_ga4_measurement' => $measurementId,
            'sitekit_gtm_container' => $containerId,
            'sitekit_tracking' => $input->getBoolean('tracking') ? '1' : '0',
            'sitekit_consent_default' => 'granted' === $input->getString('consent_default') ? 'granted' : 'denied',
        ]);

        $this->flash('confirm', $this->translator->trans('settings.site_saved', ['%site%' => $site->title], 'pdir_site_kit'));

        return $this->backToSettings($id);
    }

    /**
     * Verifies the website with the Site Verification API. Without the required
     * scopes, the form posts to the OAuth connect route instead.
     */
    #[Route(
        '%contao.backend.route_prefix%/site-kit/settings/site/{id}/verify',
        name: 'pdir_site_kit_settings_verify',
        requirements: ['id' => '\d+'],
        defaults: ['_scope' => 'backend'],
        methods: ['POST'],
    )]
    public function verifySiteOwnership(int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $site = $this->sites->find($id);

        if (null === $site) {
            throw $this->createNotFoundException();
        }

        if (!$this->client->isConnected() || !$this->siteVerification->hasScopes()) {
            $this->flash('error', $site->title.': '.$this->translator->trans('verification.missing_scopes', [], 'pdir_site_kit'));

            return $this->backToSettings($id);
        }

        $this->verifySite($this->siteVerification, $site, $this->translator);

        return $this->backToSettings($id);
    }

    private function backToSettings(int|null $siteId = null): RedirectResponse
    {
        $url = $this->generateUrl('pdir_site_kit_settings').(null !== $siteId ? '#site-'.$siteId : '');

        return new RedirectResponse($url, Response::HTTP_SEE_OTHER);
    }
}
