<?php

declare(strict_types=1);

/*
 * Google Site Kit bundle for Contao Open Source CMS
 *
 * Copyright (C) pdir GmbH <develop@pdir.de>
 */

namespace Pdir\SiteKitBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class PdirSiteKitBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('google')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('client_id')
                            ->info('OAuth client ID from the Google Cloud console. If empty, it can be entered in the Site Kit back end.')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('client_secret')
                            ->info('OAuth client secret from the Google Cloud console. If empty, it can be entered in the Site Kit back end.')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('pagespeed_api_key')
                            ->info('Optional API key for PageSpeed Insights (raises the request quota).')
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()
                ->integerNode('cache_ttl')
                    ->info('How long Google report data is cached, in seconds.')
                    ->defaultValue(3600)
                    ->min(0)
                ->end()
                ->arrayNode('dashboard')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('admins_only')
                            ->info('If false, every back end user can see the reports. Settings and the Google connection always require an administrator.')
                            ->defaultTrue()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('tracking')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('skip_preview')
                            ->info('Do not output tracking code in the front end preview.')
                            ->defaultTrue()
                        ->end()
                        ->booleanNode('skip_backend_users')
                            ->info('Do not output tracking code while a back end user is logged in.')
                            ->defaultFalse()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.yaml');

        $container->parameters()
            ->set('pdir_site_kit.google.client_id', $config['google']['client_id'])
            ->set('pdir_site_kit.google.client_secret', $config['google']['client_secret'])
            ->set('pdir_site_kit.google.pagespeed_api_key', $config['google']['pagespeed_api_key'])
            ->set('pdir_site_kit.cache_ttl', $config['cache_ttl'])
            ->set('pdir_site_kit.dashboard.admins_only', $config['dashboard']['admins_only'])
            ->set('pdir_site_kit.tracking.skip_preview', $config['tracking']['skip_preview'])
            ->set('pdir_site_kit.tracking.skip_backend_users', $config['tracking']['skip_backend_users'])
        ;
    }
}
