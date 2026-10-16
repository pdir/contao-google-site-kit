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

use Contao\CoreBundle\Controller\AbstractBackendController;
use Pdir\SiteKitBundle\Google\Exception\GoogleApiException;
use Pdir\SiteKitBundle\Module\SiteVerification;
use Pdir\SiteKitBundle\Site\SiteConfig;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Base class of the Site Kit back end pages.
 *
 * Contao 5.7 moved the base controller to Contao\CoreBundle\Controller\Backend
 * and deprecated this class, which still works in Contao 6 and will be removed in
 * Contao 7. It is the only one that exists in Contao 5.3 to 5.6, so it is used to
 * support Contao 5.3 LTS and Contao 6 from one code base.
 */
abstract class AbstractSiteKitController extends AbstractBackendController
{
    public const SESSION_FLASH = 'pdir_site_kit_flash';

    /**
     * Stores a message that is shown on the next page load.
     */
    protected function flash(string $type, string $message): void
    {
        $session = $this->container->get('request_stack')->getSession();
        $messages = $session->get(self::SESSION_FLASH, []);
        $messages = \is_array($messages) ? $messages : [];
        $messages[] = ['type' => $type, 'message' => $message];

        $session->set(self::SESSION_FLASH, $messages);
    }

    /**
     * @return list<array{type: string, message: string}>
     */
    protected function consumeFlashes(): array
    {
        $session = $this->container->get('request_stack')->getSession();
        $messages = $session->get(self::SESSION_FLASH, []);
        $session->remove(self::SESSION_FLASH);

        if (!\is_array($messages)) {
            return [];
        }

        $flashes = [];

        foreach ($messages as $message) {
            if (\is_array($message) && isset($message['type'], $message['message'])) {
                $flashes[] = ['type' => (string) $message['type'], 'message' => (string) $message['message']];
            }
        }

        return $flashes;
    }

    /**
     * Verifies the website with Google and reports the outcome as a flash message.
     */
    protected function verifySite(SiteVerification $verification, SiteConfig $site, TranslatorInterface $translator): void
    {
        try {
            $siteUrl = $verification->verify($site);
        } catch (GoogleApiException|\InvalidArgumentException $e) {
            $this->flash('error', $site->title.': '.$translator->trans('verification.failed', ['%error%' => $e->getMessage()], 'pdir_site_kit'));

            return;
        }

        $this->flash('confirm', $site->title.': '.$translator->trans('verification.done', ['%url%' => $siteUrl], 'pdir_site_kit'));
    }
}
