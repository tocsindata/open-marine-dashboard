<?php

/**
 * Project: Marine
 * Framework: Laravel 12
 * File: app/Support/LocalDateTime.php
 * Date: 2026-08-20
 * Copyright:
 *   Copyright (c) 2026 Tocsin Data / Global Incident Map.
 *   All rights reserved.
 */

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;

final class LocalDateTime
{
    public const AMBIGUOUS = 'ambiguous';

    public const INVALID = 'invalid';

    public const NONEXISTENT = 'nonexistent';

    public const VALID = 'valid';

    /**
     * Resolve a wall-clock value only when it maps to exactly one UTC instant.
     *
     * @return array{status: string, utc: DateTimeImmutable|null}
     */
    public static function resolve(string $value, string $timezone): array
    {
        $utc = new DateTimeZone('UTC');
        $wallTime = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, $utc);
        $errors = DateTimeImmutable::getLastErrors();

        if (
            $wallTime === false
            || $wallTime->format('Y-m-d\TH:i') !== $value
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        ) {
            return ['status' => self::INVALID, 'utc' => null];
        }

        $zone = new DateTimeZone($timezone);
        $wallTimestamp = $wallTime->getTimestamp();
        $transitions = $zone->getTransitions($wallTimestamp - 172800, $wallTimestamp + 172800);
        $offsets = [];

        if ($transitions !== false) {
            foreach ($transitions as $transition) {
                $offsets[(int) $transition['offset']] = true;
            }
        }

        if ($offsets === []) {
            $offsets[$zone->getOffset($wallTime)] = true;
        }

        $candidates = [];

        foreach (array_keys($offsets) as $offset) {
            $timestamp = $wallTimestamp - $offset;
            $candidate = (new DateTimeImmutable('@'.$timestamp))->setTimezone($utc);

            if ($candidate->setTimezone($zone)->format('Y-m-d\TH:i') === $value) {
                $candidates[$timestamp] = $candidate;
            }
        }

        if ($candidates === []) {
            return ['status' => self::NONEXISTENT, 'utc' => null];
        }

        if (count($candidates) > 1) {
            return ['status' => self::AMBIGUOUS, 'utc' => null];
        }

        return ['status' => self::VALID, 'utc' => reset($candidates)];
    }
}
