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
$GLOBALS['TL_LANG']['tl_page']['sitekit_tracking'] = ['Tracking-Code ausgeben', 'Gibt den Google-Analytics- bzw. Tag-Manager-Code auf allen Seiten dieser Website aus.'];
$GLOBALS['TL_LANG']['tl_page']['sitekit_consent_default'] = ['Consent Mode (Standard)', 'Startzustand des Google Consent Mode v2. „Abgelehnt“ ist DSGVO-konform; Ihr Cookie-Banner muss die Einwilligung per gtag(\'consent\', \'update\', …) erteilen.'];
$GLOBALS['TL_LANG']['tl_page']['sitekit_consent_options'] = ['denied' => 'Abgelehnt (empfohlen)', 'granted' => 'Erteilt'];
$GLOBALS['TL_LANG']['tl_page']['sitekit_ga4_measurement'] = ['GA4-Mess-ID', 'Die Mess-ID des Web-Datenstreams, z. B. G-ABC123XYZ.'];
$GLOBALS['TL_LANG']['tl_page']['sitekit_gtm_container'] = ['Tag-Manager-Container-ID', 'Die Container-ID, z. B. GTM-ABC1234. Ist GA4 bereits im Container eingerichtet, lassen Sie die GA4-Mess-ID leer, sonst wird doppelt gezählt.'];
$GLOBALS['TL_LANG']['tl_page']['sitekit_ga4_property'] = ['GA4-Property-ID', 'Die numerische Property-ID für die Berichte im Dashboard, z. B. 123456789.'];
$GLOBALS['TL_LANG']['tl_page']['sitekit_gsc_property'] = ['Search-Console-Property', 'Z. B. sc-domain:example.com oder https://www.example.com/'];
$GLOBALS['TL_LANG']['tl_page']['sitekit_gsc_verification'] = ['Search-Console-Bestätigung (HTML-Datei)', 'Der Dateiname der Bestätigungsdatei aus der Search Console, z. B. google8bfe7b6619fd8ee1.html. Site Kit liefert die Datei dann unter dieser Adresse aus, Sie müssen nichts hochladen.'];
$GLOBALS['TL_LANG']['tl_page']['sitekit_gsc_verification_invalid'] = 'Bitte geben Sie den Dateinamen der Bestätigungsdatei ein, z. B. google8bfe7b6619fd8ee1.html.';
