<?php

declare(strict_types=1);

/*
 * Google Site Kit bundle for Contao Open Source CMS
 *
 * Copyright (C) pdir GmbH <develop@pdir.de>
 */

namespace Pdir\SiteKitBundle\Dashboard;

/**
 * A reporting period and the period of the same length right before it.
 */
final class DateRange
{
    public const ALLOWED_DAYS = [7, 28, 90];

    public readonly \DateTimeImmutable $start;
    public readonly \DateTimeImmutable $end;
    public readonly \DateTimeImmutable $previousStart;
    public readonly \DateTimeImmutable $previousEnd;

    /**
     * @param int $offsetDays Days to skip at the end, e.g. because Search Console data is delayed
     */
    public function __construct(
        public readonly int $days,
        int $offsetDays = 1,
        \DateTimeImmutable|null $today = null,
    ) {
        if ($days < 1) {
            throw new \InvalidArgumentException('The range must have at least one day.');
        }

        $today = ($today ?? new \DateTimeImmutable('today'))->setTime(0, 0);

        $this->end = $today->modify(\sprintf('-%d days', $offsetDays));
        $this->start = $this->end->modify(\sprintf('-%d days', $days - 1));
        $this->previousEnd = $this->start->modify('-1 day');
        $this->previousStart = $this->previousEnd->modify(\sprintf('-%d days', $days - 1));
    }

    public static function normalizeDays(int|string|null $days): int
    {
        $days = (int) $days;

        return \in_array($days, self::ALLOWED_DAYS, true) ? $days : 28;
    }

    /**
     * @return list<string> All dates of the current period as Y-m-d
     */
    public function getDates(): array
    {
        $dates = [];

        for ($date = $this->start; $date <= $this->end; $date = $date->modify('+1 day')) {
            $dates[] = $date->format('Y-m-d');
        }

        return $dates;
    }

    public function getKey(): string
    {
        return $this->start->format('Ymd').'-'.$this->end->format('Ymd');
    }
}
