<?php

declare(strict_types=1);

/*
 * This file is part of pdir/contao-site-kit.
 *
 * (c) pdir
 *
 * @license LGPL-3.0-or-later
 */

namespace Pdir\SiteKitBundle\EventListener;

use Contao\CoreBundle\Event\ContaoCoreEvents;
use Contao\CoreBundle\Event\MenuEvent;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Adds "Site Kit" to the "System" group of the back end navigation.
 */
#[AsEventListener(ContaoCoreEvents::BACKEND_MENU_BUILD, priority: -10)]
class BackendMenuListener
{
    public function __construct(
        private readonly Security $security,
        private readonly RouterInterface $router,
        private readonly RequestStack $requestStack,
        private readonly TranslatorInterface $translator,
        private readonly bool $adminsOnly = true,
    ) {
    }

    public function __invoke(MenuEvent $event): void
    {
        if ('mainMenu' !== $event->getTree()->getName()) {
            return;
        }

        if (!$this->security->isGranted($this->adminsOnly ? 'ROLE_ADMIN' : 'ROLE_USER')) {
            return;
        }

        $category = $event->getTree()->getChild('system');

        if (null === $category) {
            return;
        }

        $route = (string) $this->requestStack->getCurrentRequest()?->attributes->get('_route');

        $node = $event->getFactory()
            ->createItem('pdir-site-kit')
            ->setLabel($this->translator->trans('menu.label', [], 'pdir_site_kit'))
            ->setExtra('translation_domain', false)
            ->setUri($this->router->generate('pdir_site_kit_dashboard'))
            ->setLinkAttribute('class', 'navigation pdir-site-kit')
            ->setLinkAttribute('title', $this->translator->trans('menu.title', [], 'pdir_site_kit'))
            ->setLinkAttribute('data-turbo', 'false')
            ->setCurrent(str_starts_with($route, 'pdir_site_kit_'))
        ;

        $category->addChild($node);
    }
}
