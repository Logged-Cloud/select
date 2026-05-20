<?php

namespace LoggedCloud\Select;

use RuntimeException;

/**
 * Loads the bundled SVG map datasets that ship with logged-cloud/select.
 *
 * The data lives in resources/data/*.json · generated from Natural Earth
 * public-domain GeoJSON by bin/build-map-data.py. Each dataset returns an
 * associative array with `viewBox` and `items` keys (plus an `outline` for
 * country-level maps that wrap clickable city points).
 *
 * Apps wanting non-UK country/city/town maps generate their own JSON in the
 * same shape and feed it directly to <x-select::map-svg-alpine :items + view-box>.
 */
class MapData
{
    /** World map · ~180 countries, each as a clickable SVG path. */
    public static function world(): array
    {
        return self::load('world.json');
    }

    /** UK country map · outline polygon + ~40 major-city points. */
    public static function uk(): array
    {
        return self::load('uk.json');
    }

    /**
     * Town-level dataset keyed by city · returns one bucket or the full set.
     * Each bucket has its own zoomed viewBox so the town-detail map renders
     * at a comparable scale regardless of where the city sits.
     */
    public static function ukTowns(?string $cityKey = null): array
    {
        $all = self::load('uk-towns.json');
        if ($cityKey === null) {
            return $all;
        }
        return $all[$cityKey] ?? ['viewBox' => '0 0 500 500', 'items' => []];
    }

    private static function load(string $file): array
    {
        $path = __DIR__.'/../resources/data/'.$file;
        if (! is_file($path)) {
            throw new RuntimeException("Map dataset not found: {$path}");
        }
        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data)) {
            throw new RuntimeException("Map dataset is not valid JSON: {$path}");
        }
        return $data;
    }
}
