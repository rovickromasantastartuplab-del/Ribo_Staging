<?php

namespace App\Services\AI\Reports;

class ReportBrandStyleResolver
{
    public function resolve(?string $brandColor = null): array
    {
        $base = $this->normalizeHex($brandColor) ?? '#10b77f';

        return [
            'brand_color' => $base,
            'brand_soft' => $this->mix($base, '#ffffff', 0.88),
            'brand_border' => $this->mix($base, '#111827', 0.35),
            'text_primary' => '#111827',
            'text_muted' => '#6b7280',
            'border' => '#e5e7eb',
            'surface' => '#f9fafb',
            'danger' => '#ef4444',
            'warning' => '#f59e0b',
        ];
    }

    private function normalizeHex(?string $value): ?string
    {
        $value = trim((string) $value);

        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            return null;
        }

        return strtolower($value);
    }

    private function mix(string $foreground, string $background, float $backgroundWeight): string
    {
        $backgroundWeight = max(0.0, min(1.0, $backgroundWeight));
        $foregroundWeight = 1.0 - $backgroundWeight;

        $fg = [
            hexdec(substr($foreground, 1, 2)),
            hexdec(substr($foreground, 3, 2)),
            hexdec(substr($foreground, 5, 2)),
        ];
        $bg = [
            hexdec(substr($background, 1, 2)),
            hexdec(substr($background, 3, 2)),
            hexdec(substr($background, 5, 2)),
        ];

        $r = (int) round(($fg[0] * $foregroundWeight) + ($bg[0] * $backgroundWeight));
        $g = (int) round(($fg[1] * $foregroundWeight) + ($bg[1] * $backgroundWeight));
        $b = (int) round(($fg[2] * $foregroundWeight) + ($bg[2] * $backgroundWeight));

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
}

