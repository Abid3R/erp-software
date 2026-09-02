<?php

namespace App\Filament\Widgets\Dashboard\Concerns;

/**
 * Shared visual language for the dashboard charts so they read as one modern,
 * enterprise-grade system: cohesive palette, soft translucent fills, rounded
 * bars, restrained grids and pill-style legends/tooltips. Purely presentational
 * — no data logic lives here.
 */
trait ChartTheme
{
    // Cohesive modern palette (hex).
    protected const C_BLUE = '#2563eb';
    protected const C_EMERALD = '#10b981';
    protected const C_ROSE = '#f43f5e';
    protected const C_AMBER = '#f59e0b';
    protected const C_VIOLET = '#8b5cf6';
    protected const C_SKY = '#0ea5e9';
    protected const C_TEAL = '#14b8a6';
    protected const C_SLATE = '#94a3b8';

    /** Build an rgba() string from a hex colour. */
    protected static function rgba(string $hex, float $alpha): string
    {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return "rgba({$r}, {$g}, {$b}, {$alpha})";
    }

    /** Modern legend + tooltip styling shared by every chart. */
    protected function themePlugins(bool $legend = true, string $legendPosition = 'bottom'): array
    {
        return [
            'legend' => [
                'display' => $legend,
                'position' => $legendPosition,
                'labels' => [
                    'usePointStyle' => true,
                    'pointStyle' => 'circle',
                    'boxWidth' => 8,
                    'boxHeight' => 8,
                    'padding' => 16,
                    'color' => self::C_SLATE,
                    'font' => ['size' => 12, 'weight' => '500'],
                ],
            ],
            'tooltip' => [
                'backgroundColor' => 'rgba(15, 23, 42, 0.92)',
                'padding' => 12,
                'cornerRadius' => 10,
                'usePointStyle' => true,
                'boxPadding' => 6,
                'titleColor' => '#f8fafc',
                'bodyColor' => '#e2e8f0',
                'titleFont' => ['size' => 12, 'weight' => '600'],
                'bodyFont' => ['size' => 12],
            ],
        ];
    }

    /**
     * Value/category axis styling. Pass the axis that carries the numeric scale
     * ('y' for vertical bars & lines, 'x' for horizontal bars); the other axis
     * is treated as the category axis (no grid lines).
     */
    protected function themeScales(string $valueAxis = 'y'): array
    {
        $categoryAxis = $valueAxis === 'y' ? 'x' : 'y';

        return [
            $valueAxis => [
                'beginAtZero' => true,
                'border' => ['display' => false],
                'grid' => ['color' => 'rgba(148, 163, 184, 0.16)', 'drawTicks' => false],
                'ticks' => ['color' => self::C_SLATE, 'font' => ['size' => 11], 'padding' => 8],
            ],
            $categoryAxis => [
                'border' => ['display' => false],
                'grid' => ['display' => false],
                'ticks' => ['color' => self::C_SLATE, 'font' => ['size' => 11], 'padding' => 6],
            ],
        ];
    }

    /** Smooth, professional entrance animation. */
    protected function themeAnimation(): array
    {
        return ['duration' => 650, 'easing' => 'easeOutQuart'];
    }
}
