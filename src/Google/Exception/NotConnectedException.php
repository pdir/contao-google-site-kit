<?php

declare(strict_types=1);

/*
 * This file is part of pdir/contao-site-kit.
 *
 * (c) pdir
 *
 * @license LGPL-3.0-or-later
 */

namespace Pdir\SiteKitBundle\Google\Exception;

/**
 * There is no (valid) connection to a Google account.
 */
class NotConnectedException extends GoogleApiException
{
}
