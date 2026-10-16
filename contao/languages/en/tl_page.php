<?php

declare(strict_types=1);

/*
 * This file is part of pdir/contao-site-kit.
 *
 * (c) pdir
 *
 * @license LGPL-3.0-or-later
 */

$GLOBALS['TL_LANG']['tl_page']['sitekit_legend'] = 'Google Site Kit';
$GLOBALS['TL_LANG']['tl_page']['sitekit_tracking'] = ['Output tracking code', 'Outputs the Google Analytics or Tag Manager code on all pages of this website.'];
$GLOBALS['TL_LANG']['tl_page']['sitekit_consent_default'] = ['Consent mode default', 'Initial state of Google Consent Mode v2. "Denied" is GDPR compliant; your cookie banner has to grant consent via gtag(\'consent\', \'update\', …).'];
$GLOBALS['TL_LANG']['tl_page']['sitekit_consent_options'] = ['denied' => 'Denied (recommended)', 'granted' => 'Granted'];
$GLOBALS['TL_LANG']['tl_page']['sitekit_ga4_measurement'] = ['GA4 measurement ID', 'The measurement ID of the web data stream, e.g. G-ABC123XYZ.'];
$GLOBALS['TL_LANG']['tl_page']['sitekit_gtm_container'] = ['Tag Manager container ID', 'The container ID, e.g. GTM-ABC1234. If GA4 is already set up in the container, leave the GA4 measurement ID empty to avoid double counting.'];
$GLOBALS['TL_LANG']['tl_page']['sitekit_ga4_property'] = ['GA4 property ID', 'The numeric property ID used for the dashboard reports, e.g. 123456789.'];
$GLOBALS['TL_LANG']['tl_page']['sitekit_gsc_property'] = ['Search Console property', 'E.g. sc-domain:example.com or https://www.example.com/'];
$GLOBALS['TL_LANG']['tl_page']['sitekit_gsc_verification'] = ['Search Console verification (HTML file)', 'The file name of the verification file from Search Console, e.g. google8bfe7b6619fd8ee1.html. Site Kit then serves the file at this address, there is nothing to upload.'];
$GLOBALS['TL_LANG']['tl_page']['sitekit_gsc_verification_invalid'] = 'Please enter the file name of the verification file, e.g. google8bfe7b6619fd8ee1.html.';
