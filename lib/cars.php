<?php

declare(strict_types=1);

/**
 * cars.php - look up a car name from data/cars.json.
 *
 * The JSON is produced by tools/scrape-cars.php. If it's missing, lookups
 * return null and the TUI falls back to showing the raw car id.
 */

/** Load the car table once (car_id => record), cached for the process. */
function gt7_load_cars(): array
{
    static $cars = null;
    if ($cars === null) {
        $path = __DIR__ . '/../data/cars.json';
        $cars = is_file($path) ? (json_decode((string) file_get_contents($path), true) ?: []) : [];
    }
    return $cars;
}

/** Short car name for a car id, or null if unknown. */
function gt7_car_name(int $id): ?string
{
    $cars = gt7_load_cars();
    return $cars[$id]['nameShort'] ?? $cars[$id]['nameLong'] ?? null;
}
