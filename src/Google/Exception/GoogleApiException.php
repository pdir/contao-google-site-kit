<?php

declare(strict_types=1);

/*
 * Google Site Kit bundle for Contao Open Source CMS
 *
 * Copyright (C) pdir GmbH <develop@pdir.de>
 */

namespace Pdir\SiteKitBundle\Google\Exception;

/**
 * A request to a Google API failed. The message is the error Google returned and
 * is safe to show to back end administrators.
 */
class GoogleApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $statusCode = 0,
        private readonly string $reason = '',
        \Throwable|null $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Google's error status, e.g. PERMISSION_DENIED or invalid_grant.
     */
    public function getReason(): string
    {
        return $this->reason;
    }

    public function isPermissionError(): bool
    {
        return 403 === $this->statusCode || 'PERMISSION_DENIED' === $this->reason;
    }

    public function isQuotaError(): bool
    {
        return 429 === $this->statusCode || 'RESOURCE_EXHAUSTED' === $this->reason;
    }
}
