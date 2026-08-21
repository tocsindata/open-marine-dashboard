<?php

/**
 * Project: Marine
 * Framework: Laravel 12
 * File: app/Support/UnitPresentation.php
 * Date: 2026-08-20
 * SPDX-License-Identifier: AGPL-3.0-only
 * Copyright (c) 2026 Tocsin Data.
 */

namespace App\Support;

final class UnitPresentation
{
    private const METRES_PER_FOOT = 0.3048;

    /**
     * Format a non-clearance reference dimension for the owner's preference.
     *
     * Draft, route, forecast, chart, tide/current, and other safety/source
     * values must retain their separately approved units and must not use this.
     */
    public static function referenceLength(int|float|string|null $metres, ?string $unitSystem): ?string
    {
        if ($metres === null || $metres === '') {
            return null;
        }

        $metric = number_format((float) $metres, 2, '.', '');

        if ($unitSystem !== 'imperial') {
            return "{$metric} m";
        }

        $feet = number_format((float) $metres / self::METRES_PER_FOOT, 2, '.', '');

        return "≈ {$feet} ft ({$metric} m)";
    }
}
