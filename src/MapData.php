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

    /** UK country map · ~16 clickable region polygons (Greater London,
     *  South East, Scotland, etc) grouped from Natural Earth admin-1.
     *  Click a region to drill into uk-<region>.json. */
    public static function uk(): array
    {
        return self::load('uk.json');
    }

    /** A UK region's sub-region polygons · pass 'greater-london',
     *  'south-east', 'scotland', etc. Returns an empty list if the
     *  region key isn't recognised. */
    public static function ukRegion(string $regionKey): array
    {
        $file = 'uk-'.preg_replace('/[^a-z0-9-]+/', '', strtolower($regionKey)).'.json';
        $path = __DIR__.'/../resources/data/'.$file;
        if (! is_file($path)) {
            return ['viewBox' => '0 0 500 500', 'items' => []];
        }
        return self::load($file);
    }

    /**
     * Hand-curated town markers per major UK city · returns one bucket or
     * the full set. Kept around as an alternative to the polygon-based
     * uk-<region>.json drilldown for point-marker aesthetics.
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
