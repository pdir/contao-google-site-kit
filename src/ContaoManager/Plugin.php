<?php

declare(strict_types=1);

/*
 * This file is part of pdir/contao-site-kit.
 *
 * (c) pdir
 *
 * @license LGPL-3.0-or-later
 */

namespace Pdir\SiteKitBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\ManagerPlugin\Routing\RoutingPluginInterface;
use Pdir\SiteKitBundle\PdirSiteKitBundle;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouteCollection;

class Plugin implements BundlePluginInterface, RoutingPluginInterface
{
    public function getBundles(ParserInterface $parser): array
    {
        return [
            BundleConfig::create(PdirSiteKitBundle::class)->setLoadAfter([ContaoCoreBundle::class]),
        ];
    }

    public function getRouteCollection(LoaderResolverInterface $resolver, KernelInterface $kernel): RouteCollection|null
    {
        $file = __DIR__.'/../../config/routes.yaml';

        $loader = $resolver->resolve($file);

        return false === $loader ? null : $loader->load($file);
    }
}
