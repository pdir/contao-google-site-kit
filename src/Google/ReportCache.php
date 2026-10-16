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

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Caches Google API results. Errors are never cached, because the callback
 * throws before a value is stored.
 */
class ReportCache
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly int $cacheTtl,
    ) {
    }

    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function get(string $key, callable $callback, bool $refresh = false, int|null $ttl = null): mixed
    {
        $key = 'pdir_site_kit.report.'.hash('xxh128', $key);

        if ($refresh) {
            $this->cache->delete($key);
        }

        $ttl ??= $this->cacheTtl;

        if ($ttl <= 0) {
            return $callback();
        }

        return $this->cache->get(
            $key,
            static function (ItemInterface $item) use ($callback, $ttl): mixed {
                $item->expiresAfter($ttl);

                return $callback();
            },
        );
    }
}
