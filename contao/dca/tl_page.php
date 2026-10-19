<?php

declare(strict_types=1);

/*
 * Google Site Kit bundle for Contao Open Source CMS
 *
 * Copyright (C) pdir GmbH <develop@pdir.de>
 */

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Pdir\SiteKitBundle\Site\SiteConfig;

// The fields can also be set on the Site Kit page (with property lists loaded
// from Google). They are kept in the page structure so that they are versioned
// and can be edited without a Google connection.
$GLOBALS['TL_DCA']['tl_page']['fields']['sitekit_tracking'] = [
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50 m12'],
    'sql' => ['type' => 'boolean', 'default' => false],
];

$GLOBALS['TL_DCA']['tl_page']['fields']['sitekit_consent_default'] = [
    'exclude' => true,
    'inputType' => 'select',
    'options' => ['denied', 'granted'],
    'reference' => &$GLOBALS['TL_LANG']['tl_page']['sitekit_consent_options'],
    'eval' => ['tl_class' => 'w50'],
    'sql' => ['type' => 'string', 'length' => 8, 'default' => 'denied'],
];

$GLOBALS['TL_DCA']['tl_page']['fields']['sitekit_ga4_measurement'] = [
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['maxlength' => 32, 'rgxp' => 'custom', 'customRgxp' => '/^G-[A-Z0-9]{4,}$/', 'tl_class' => 'w50'],
    'sql' => ['type' => 'string', 'length' => 32, 'default' => ''],
];

$GLOBALS['TL_DCA']['tl_page']['fields']['sitekit_gtm_container'] = [
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['maxlength' => 32, 'rgxp' => 'custom', 'customRgxp' => '/^GTM-[A-Z0-9]{4,}$/', 'tl_class' => 'w50'],
    'sql' => ['type' => 'string', 'length' => 32, 'default' => ''],
];

$GLOBALS['TL_DCA']['tl_page']['fields']['sitekit_ga4_property'] = [
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['maxlength' => 32, 'rgxp' => 'natural', 'tl_class' => 'w50'],
    'sql' => ['type' => 'string', 'length' => 32, 'default' => ''],
];

$GLOBALS['TL_DCA']['tl_page']['fields']['sitekit_gsc_property'] = [
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['maxlength' => 255, 'decodeEntities' => true, 'tl_class' => 'w50'],
    'sql' => ['type' => 'string', 'length' => 255, 'default' => ''],
];

$GLOBALS['TL_DCA']['tl_page']['fields']['sitekit_gsc_verification'] = [
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['maxlength' => 128, 'decodeEntities' => true, 'tl_class' => 'w50'],
    'save_callback' => [
        static function (mixed $value): string {
            $token = SiteConfig::normalizeVerificationToken((string) $value);

            if (null === $token) {
                throw new \RuntimeException($GLOBALS['TL_LANG']['tl_page']['sitekit_gsc_verification_invalid'] ?? 'Invalid verification token.');
            }

            return $token;
        },
    ],
    'sql' => ['type' => 'string', 'length' => 128, 'default' => ''],
];

$manipulator = PaletteManipulator::create()
    ->addLegend('sitekit_legend', 'global_legend', PaletteManipulator::POSITION_AFTER, true)
    ->addField(
        ['sitekit_tracking', 'sitekit_consent_default', 'sitekit_ga4_measurement', 'sitekit_gtm_container', 'sitekit_ga4_property', 'sitekit_gsc_property', 'sitekit_gsc_verification'],
        'sitekit_legend',
        PaletteManipulator::POSITION_APPEND,
    )
;

foreach (['root', 'rootfallback'] as $palette) {
    if (isset($GLOBALS['TL_DCA']['tl_page']['palettes'][$palette])) {
        $manipulator->applyToPalette($palette, 'tl_page');
    }
}
