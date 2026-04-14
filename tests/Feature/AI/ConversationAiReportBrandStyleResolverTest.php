<?php

use App\Services\AI\Reports\ReportBrandStyleResolver;

it('returns print-safe style tokens from brand color', function () {
    $resolver = app(ReportBrandStyleResolver::class);
    $tokens = $resolver->resolve('#10b77f');

    expect($tokens['brand_color'])->toBe('#10b77f');
    expect($tokens['brand_soft'])->toStartWith('#');
    expect($tokens['brand_border'])->toStartWith('#');
});

it('falls back to default brand color when invalid input is passed', function () {
    $resolver = app(ReportBrandStyleResolver::class);
    $tokens = $resolver->resolve('not-a-color');

    expect($tokens['brand_color'])->toBe('#10b77f');
});

