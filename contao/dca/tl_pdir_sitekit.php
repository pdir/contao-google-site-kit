<?php

declare(strict_types=1);

/*
 * Google Site Kit bundle for Contao Open Source CMS
 *
 * Copyright (C) pdir GmbH <develop@pdir.de>
 */

// Key/value storage for the Google connection. Secrets are stored encrypted,
// see Pdir\SiteKitBundle\Google\Crypto. There is no back end module for this
// table; it is managed on the Site Kit page.
$GLOBALS['TL_DCA']['tl_pdir_sitekit'] = [
    'config' => [
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'name' => 'unique',
            ],
        ],
    ],
    'fields' => [
        'id' => [
            'sql' => ['type' => 'integer', 'unsigned' => true, 'autoincrement' => true],
        ],
        'tstamp' => [
            'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0],
        ],
        'name' => [
            'sql' => ['type' => 'string', 'length' => 64, 'default' => ''],
        ],
        'value' => [
            'sql' => ['type' => 'text', 'notnull' => false],
        ],
    ],
];
