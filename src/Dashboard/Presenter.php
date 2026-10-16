<?php

declare(strict_types=1);

/*
 * This file is part of pdir/contao-site-kit.
 *
 * (c) pdir
 *
 * @license LGPL-3.0-or-later
 */

namespace Pdir\SiteKitBundle\Dashboard;

/**
 * Formats report values for the dashboard (locale aware, no intl dependency).
 */
class Presenter
{
    private string $decimalPoint = '.';
    private string $thousandsSeparator = ',';

    public function setLocale(string $locale): void
    {
        $language = strtolower(substr($locale, 0, 2));

        [$this->decimalPoint, $this->thousandsSeparator] = match ($language) {
            'de', 'es', 'it', 'nl', 'pt', 'da', 'id', 'tr' => [',', '.'],
            'fr', 'cs', 'pl', 'ru', 'sv', 'fi', 'nb', 'sk', 'uk' => [',', "\u{202F}"],
            default => ['.', ','],
        };
    }

    public function number(float|int $value, int $decimals = 0): string
    {
        return number_format((float) $value, $decimals, $this->decimalPoint, $this->thousandsSeparator);
    }

    /**
     * Compact number, e.g. 12.3k or 1.2M, for large values.
     */
    public function compact(float|int $value): string
    {
        $value = (float) $value;

        return match (true) {
            abs($value) >= 1_000_000 => $this->number($value / 1_000_000, 1).' M',
            abs($value) >= 10_000 => $this->number($value / 1_000, 1).' k',
            default => $this->number($value),
        };
    }

    /**
     * @param float $ratio e.g. 0.123 for 12.3 %
     */
    public function percent(float $ratio, int $decimals = 1): string
    {
        return $this->number($ratio * 100, $decimals)."\u{202F}%";
    }

    public function duration(float $seconds): string
    {
        $seconds = (int) round($seconds);

        return \sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    /**
     * Relative change between two periods.
     *
     * @param bool $lowerIsBetter e.g. for the average position in Search Console
     *
     * @return array{label: string, direction: string, positive: bool}|null
     */
    public function change(float|int $current, float|int $previous, bool $lowerIsBetter = false): array|null
    {
        if (0.0 === (float) $previous) {
            return null;
        }

        $change = ((float) $current - (float) $previous) / abs((float) $previous);

        if (abs($change) < 0.0005) {
            return ['label' => $this->percent(0), 'direction' => 'flat', 'positive' => true];
        }

        $direction = $change > 0 ? 'up' : 'down';

        return [
            'label' => ($change > 0 ? '+' : '−').$this->percent(abs($change)),
            'direction' => $direction,
            'positive' => $lowerIsBetter ? 'down' === $direction : 'up' === $direction,
        ];
    }

    /**
     * Builds the SVG path data for a small line chart.
     *
     * @param array<array-key, int|float> $values
     *
     * @return array{line: string, area: string, width: int, height: int, max: float}|null
     */
    public function sparkline(array $values, int $width = 600, int $height = 120): array|null
    {
        $values = array_values(array_map('floatval', $values));
        $count = \count($values);

        if ($count < 2) {
            return null;
        }

        $max = max($values) ?: 1.0;
        $padding = 4;
        $points = [];

        foreach ($values as $i => $value) {
            $x = $i / ($count - 1) * $width;
            $y = $height - $padding - ($value / $max) * ($height - 2 * $padding);
            $points[] = \sprintf('%.1f,%.1f', $x, $y);
        }

        $line = 'M'.implode(' L', $points);

        return [
            'line' => $line,
            'area' => $line.\sprintf(' L%d,%d L0,%d Z', $width, $height, $height),
            'width' => $width,
            'height' => $height,
            'max' => max($values),
        ];
    }
}
