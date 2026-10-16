<?php

declare(strict_types=1);

/*
 * This file is part of pdir/contao-site-kit.
 *
 * (c) pdir
 *
 * @license LGPL-3.0-or-later
 */

namespace Pdir\SiteKitBundle\Google;

/**
 * Provides the OAuth client credentials. Values from the bundle configuration
 * (e.g. environment variables) take precedence over values entered in the back end.
 */
class CredentialProvider
{
    public function __construct(
        private readonly SettingsStore $settings,
        private readonly string|null $configuredClientId = null,
        private readonly string|null $configuredClientSecret = null,
    ) {
    }

    public function isConfiguredExternally(): bool
    {
        return '' !== (string) $this->configuredClientId && '' !== (string) $this->configuredClientSecret;
    }

    public function getClientId(): string|null
    {
        if ($this->isConfiguredExternally()) {
            return $this->configuredClientId;
        }

        $id = $this->settings->get(SettingsStore::CLIENT_ID);

        return '' === (string) $id ? null : $id;
    }

    public function getClientSecret(): string|null
    {
        if ($this->isConfiguredExternally()) {
            return $this->configuredClientSecret;
        }

        return $this->settings->getSecret(SettingsStore::CLIENT_SECRET);
    }

    public function hasCredentials(): bool
    {
        return null !== $this->getClientId() && null !== $this->getClientSecret();
    }
}
