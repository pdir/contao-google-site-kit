<?php

declare(strict_types=1);

/*
 * Google Site Kit bundle for Contao Open Source CMS
 *
 * Copyright (C) pdir GmbH <develop@pdir.de>
 */

namespace Pdir\SiteKitBundle\Google\Exception;

/**
 * There is no (valid) connection to a Google account.
 */
class NotConnectedException extends GoogleApiException
{
}
