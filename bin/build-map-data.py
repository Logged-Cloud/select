#!/usr/bin/env python3
"""
Build the bundled SVG map datasets for logged-cloud/select.

Sources (Natural Earth, public domain):
  - ne_110m_admin_0_countries.geojson      → resources/data/world.json
  - ne_10m_admin_1_states_provinces.geojson (filtered to GB)
                                            → resources/data/uk-regions.json
  - ne_10m_populated_places.geojson         → resources/data/uk-towns.json

Each output JSON is a list of items shaped:
  { "key": str, "title": str, "path": "M… Z", "bbox": [x, y, w, h] }
  (for towns: { "key", "title", "cx", "cy" } — points rather than paths)

Projection: equirectangular (lon→x, -lat→y) so the math stays trivial.
Coordinates are rounded to integers in projected space, which is the
single biggest contributor to file-size + the deciding factor in the
"super simple SVG" aesthetic.

Run from anywhere:
    python3 bin/build-map-data.py /tmp/source/  resources/data/
"""

import json
import sys
import os
import re

# Output viewBox dimensions · everything is projected into these.
WORLD_W, WORLD_H = 1000, 500
UK_BBOX_LON = (-8.5, 2.0)        # west, east
UK_BBOX_LAT = (49.5, 61.0)       # south, north
UK_W, UK_H = 500, 600

ALIAS_OVERRIDES = {
    # Natural Earth ISO codes are mostly 3-letter; some are -99 for disputed.
    # Map a friendlier key the consumer's `items[].parent` can target.
    'GBR': 'gb', 'USA': 'us', 'FRA': 'fr', 'DEU': 'de', 'ESP': 'es',
    'ITA': 'it', 'JPN': 'jp', 'AUS': 'au', 'CAN': 'ca', 'BRA': 'br',
    'CHN': 'cn', 'IND': 'in', 'RUS': 'ru', 'IRL': 'ie', 'NLD': 'nl',
    'BEL': 'be', 'PRT': 'pt', 'POL': 'pl', 'SWE': 'se', 'NOR': 'no',
    'FIN': 'fi', 'DNK': 'dk', 'CHE': 'ch', 'AUT': 'at', 'GRC': 'gr',
    'TUR': 'tr', 'MEX': 'mx', 'ARG': 'ar', 'CHL': 'cl', 'EGY': 'eg',
    'ZAF': 'za', 'NGA': 'ng', 'KEN': 'ke', 'KOR': 'kr', 'IDN': 'id',
    'THA': 'th', 'VNM': 'vn', 'PHL': 'ph', 'NZL': 'nz', 'PER': 'pe',
    'COL': 'co', 'SAU': 'sa', 'IRN': 'ir', 'IRQ': 'iq', 'ISR': 'il',
    'UKR': 'ua', 'CZE': 'cz', 'HUN': 'hu', 'ROU': 'ro', 'BGR': 'bg',
}


def project_world(lon, lat):
    """Equirectangular world projection · lon/lat → (x, y) integer."""
    x = round((lon + 180.0) * WORLD_W / 360.0)
    y = round((90.0 - lat) * WORLD_H / 180.0)
    return x, y


def project_uk(lon, lat):
    """UK-bbox equirectangular · scales the UK box into UK_W × UK_H."""
    lon_w, lon_e = UK_BBOX_LON
    lat_s, lat_n = UK_BBOX_LAT
    x = round((lon - lon_w) * UK_W / (lon_e - lon_w))
    y = round((lat_n - lat) * UK_H / (lat_n - lat_s))
    return x, y


def ring_to_path(ring, project):
    """Project a ring (list of [lon, lat]) into an "M x y L x y … Z" SVG path.
    Drops consecutive duplicate projected points so a 1000-vertex ring at our
    coarse projection becomes maybe 50 unique commands."""
    pts = []
    last = None
    for lon, lat in ring:
        p = project(lon, lat)
        if p != last:
            pts.append(p)
            last = p
    if len(pts) < 3:
        return ''
    out = ['M', str(pts[0][0]), str(pts[0][1])]
    for p in pts[1:]:
        out.append('L')
        out.append(str(p[0]))
        out.append(str(p[1]))
    out.append('Z')
    return ' '.join(out)


def geom_to_path(geom, project):
    """A GeoJSON Polygon / MultiPolygon → a single SVG d-string concatenating
    every outer ring (we skip inner holes for the "simple" aesthetic)."""
    if geom is None:
        return ''
    if geom['type'] == 'Polygon':
        polys = [geom['coordinates']]
    elif geom['type'] == 'MultiPolygon':
        polys = geom['coordinates']
    else:
        return ''
    paths = []
    for poly in polys:
        # poly[0] is the outer ring; skip poly[1:] (holes).
        p = ring_to_path(poly[0], project)
        if p:
            paths.append(p)
    return ' '.join(paths)


def bbox_of_path(path):
    """Quick bbox by scanning the integer coords inside the path."""
    nums = [int(n) for n in re.findall(r'-?\d+', path)]
    if not nums:
        return None
    xs = nums[0::2]
    ys = nums[1::2]
    return [min(xs), min(ys), max(xs) - min(xs), max(ys) - min(ys)]


def build_world(src_dir, out_dir):
    path = os.path.join(src_dir, 'ne_110m_admin_0_countries.geojson')
    with open(path, 'r') as f:
        data = json.load(f)
    items = []
    for feat in data['features']:
        props = feat['properties']
        iso = (props.get('ADM0_A3') or props.get('ISO_A3') or '').strip()
        name = props.get('NAME') or props.get('ADMIN') or ''
        if not iso or iso == '-99' or not name:
            continue
        key = ALIAS_OVERRIDES.get(iso, iso.lower())
        d = geom_to_path(feat['geometry'], project_world)
        if not d:
            continue
        bbox = bbox_of_path(d)
        items.append({'key': key, 'title': name, 'path': d, 'bbox': bbox})
    items.sort(key=lambda x: x['title'])
    out_path = os.path.join(out_dir, 'world.json')
    with open(out_path, 'w') as f:
        json.dump({
            'viewBox': f'0 0 {WORLD_W} {WORLD_H}',
            'items': items,
        }, f, separators=(',', ':'))
    print(f'world.json   {len(items)} items   {os.path.getsize(out_path)} bytes')


def build_uk(src_dir, out_dir):
    """UK map = ~16 clickable region polygons grouped from admin-1 by the
    `region` field. Each region is a single SVG path concatenating every
    sub-feature's outer ring, so the whole region behaves as one click
    target even though it visually shows sub-borders."""
    admin1_path = os.path.join(src_dir, 'ne_10m_admin_1_states_provinces.geojson')
    with open(admin1_path, 'r') as f:
        admin1 = json.load(f)

    # Group GB features by their `region` field.
    by_region = {}
    for feat in admin1['features']:
        props = feat['properties']
        if props.get('admin') != 'United Kingdom':
            continue
        region = (props.get('region') or '').strip()
        if not region:
            continue
        by_region.setdefault(region, []).append(feat)

    items = []
    for region, feats in by_region.items():
        # Concat every sub-feature's outer-ring path → one combined SVG d-string.
        paths = []
        for feat in feats:
            p = geom_to_path(feat['geometry'], project_uk)
            if p:
                paths.append(p)
        d = ' '.join(paths)
        if not d:
            continue
        key = re.sub(r'[^a-z0-9]+', '-', region.lower()).strip('-')
        items.append({'key': key, 'title': region, 'path': d, 'bbox': bbox_of_path(d)})
    items.sort(key=lambda x: x['title'])

    out_path = os.path.join(out_dir, 'uk.json')
    with open(out_path, 'w') as f:
        json.dump({
            'viewBox': f'0 0 {UK_W} {UK_H}',
            'items': items,
        }, f, separators=(',', ':'))
    print(f'uk.json   {len(items)} regions   {os.path.getsize(out_path)} bytes')

    # Per-region sub-feature datasets · uk-greater-london.json, uk-south-east.json …
    # Each one re-projects into its own region-tight bbox so the zoom is useful.
    for region, feats in by_region.items():
        key = re.sub(r'[^a-z0-9]+', '-', region.lower()).strip('-')
        # Compute the region's lon/lat bbox from sub-feature coords.
        lons, lats = [], []
        for feat in feats:
            geom = feat['geometry']
            polys = geom.get('coordinates') or []
            if geom['type'] == 'Polygon':
                polys = [polys]
            for poly in polys:
                for ring in poly:
                    for lon, lat in ring:
                        lons.append(lon)
                        lats.append(lat)
        if not lons:
            continue
        # Add a small padding · 5% of the span each side.
        lon_w, lon_e = min(lons), max(lons)
        lat_s, lat_n = min(lats), max(lats)
        pad_lon = (lon_e - lon_w) * 0.05 or 0.05
        pad_lat = (lat_n - lat_s) * 0.05 or 0.05
        lon_w -= pad_lon; lon_e += pad_lon
        lat_s -= pad_lat; lat_n += pad_lat
        view_w, view_h = 500, 500

        def project(lon, lat, _w=lon_w, _e=lon_e, _s=lat_s, _n=lat_n, _vw=view_w, _vh=view_h):
            x = round((lon - _w) * _vw / (_e - _w))
            y = round((_n - lat) * _vh / (_n - _s))
            return x, y

        sub_items = []
        for feat in feats:
            props = feat['properties']
            name = props.get('name') or props.get('name_en') or ''
            if not name:
                continue
            d = geom_to_path(feat['geometry'], project)
            if not d:
                continue
            sub_key = re.sub(r'[^a-z0-9]+', '-', name.lower()).strip('-')
            sub_items.append({
                'key': sub_key,
                'title': name,
                'path': d,
                'parent': key,
                'bbox': bbox_of_path(d),
            })
        sub_items.sort(key=lambda x: x['title'])
        out_sub = os.path.join(out_dir, f'uk-{key}.json')
        with open(out_sub, 'w') as f:
            json.dump({
                'viewBox': f'0 0 {view_w} {view_h}',
                'items': sub_items,
            }, f, separators=(',', ':'))
        print(f'uk-{key}.json   {len(sub_items)} sub-regions   {os.path.getsize(out_sub)} bytes')


def build_uk_towns(src_dir, out_dir):
    """Level-3 · towns / boroughs scoped per major UK city. Each city's town
    set is its own viewBox (a zoom-crop centred on the city) and the items
    are points referenced by parent = city key. Hand-curated so the demo
    has something to drill into without 60k+ records of OS open data."""
    HAND_CURATED = {
        'london': {
            'centre_lon': -0.118, 'centre_lat': 51.509, 'span_deg': 0.8,
            'towns': [
                ('westminster', 'Westminster',  -0.135, 51.4975),
                ('camden', 'Camden',            -0.142, 51.5290),
                ('islington', 'Islington',      -0.103, 51.5360),
                ('hackney', 'Hackney',          -0.055, 51.5450),
                ('greenwich', 'Greenwich',       0.005, 51.4830),
                ('lambeth', 'Lambeth',          -0.117, 51.4970),
                ('southwark', 'Southwark',      -0.083, 51.5030),
                ('croydon', 'Croydon',          -0.099, 51.3760),
                ('barnet', 'Barnet',            -0.205, 51.6520),
                ('ealing', 'Ealing',            -0.302, 51.5130),
                ('richmond', 'Richmond',        -0.305, 51.4610),
                ('bromley', 'Bromley',           0.014, 51.4060),
                ('kingston', 'Kingston',        -0.300, 51.4120),
                ('newham', 'Newham',             0.030, 51.5260),
                ('redbridge', 'Redbridge',       0.075, 51.5760),
                ('havering', 'Havering',         0.215, 51.5780),
            ],
        },
        'manchester': {
            'centre_lon': -2.244, 'centre_lat': 53.481, 'span_deg': 0.5,
            'towns': [
                ('manchester-city', 'Manchester City', -2.244, 53.481),
                ('salford', 'Salford',                 -2.293, 53.487),
                ('stockport', 'Stockport',             -2.157, 53.408),
                ('oldham', 'Oldham',                   -2.108, 53.541),
                ('rochdale', 'Rochdale',               -2.158, 53.615),
                ('bury', 'Bury',                       -2.298, 53.591),
                ('bolton', 'Bolton',                   -2.430, 53.578),
                ('wigan', 'Wigan',                     -2.633, 53.545),
                ('trafford', 'Trafford',               -2.301, 53.435),
                ('tameside', 'Tameside',               -2.080, 53.483),
            ],
        },
        'birmingham': {
            'centre_lon': -1.898, 'centre_lat': 52.486, 'span_deg': 0.6,
            'towns': [
                ('birmingham-city', 'Birmingham City', -1.898, 52.486),
                ('solihull', 'Solihull',               -1.778, 52.412),
                ('walsall', 'Walsall',                 -1.982, 52.586),
                ('dudley', 'Dudley',                   -2.083, 52.510),
                ('sandwell', 'Sandwell',               -1.994, 52.518),
                ('wolverhampton', 'Wolverhampton',     -2.128, 52.587),
                ('coventry', 'Coventry',               -1.510, 52.408),
                ('sutton-coldfield', 'Sutton Coldfield', -1.823, 52.563),
            ],
        },
        'glasgow': {
            'centre_lon': -4.252, 'centre_lat': 55.864, 'span_deg': 0.5,
            'towns': [
                ('glasgow-city', 'Glasgow City',      -4.252, 55.864),
                ('paisley', 'Paisley',                -4.424, 55.846),
                ('east-kilbride', 'East Kilbride',    -4.176, 55.764),
                ('hamilton', 'Hamilton',              -4.040, 55.778),
                ('motherwell', 'Motherwell',          -3.991, 55.793),
                ('clydebank', 'Clydebank',            -4.402, 55.901),
                ('rutherglen', 'Rutherglen',          -4.211, 55.825),
                ('kirkintilloch', 'Kirkintilloch',    -4.156, 55.939),
            ],
        },
        'edinburgh': {
            'centre_lon': -3.189, 'centre_lat': 55.953, 'span_deg': 0.5,
            'towns': [
                ('edinburgh-city', 'Edinburgh City',  -3.189, 55.953),
                ('leith', 'Leith',                    -3.174, 55.975),
                ('musselburgh', 'Musselburgh',        -3.054, 55.943),
                ('dalkeith', 'Dalkeith',              -3.067, 55.895),
                ('penicuik', 'Penicuik',              -3.220, 55.826),
                ('livingston', 'Livingston',          -3.523, 55.886),
            ],
        },
    }
    out = {}
    for city_key, conf in HAND_CURATED.items():
        cx, cy = conf['centre_lon'], conf['centre_lat']
        span = conf['span_deg']
        # Local viewBox: tight crop around the city · 500 wide so every
        # town map shares a comparable scale regardless of where it sits.
        view_w, view_h = 500, 500
        west, east = cx - span / 2, cx + span / 2
        south, north = cy - span / 2, cy + span / 2

        def project(lon, lat, west=west, east=east, south=south, north=north,
                    view_w=view_w, view_h=view_h):
            x = round((lon - west) * view_w / (east - west))
            y = round((north - lat) * view_h / (north - south))
            return x, y

        items = []
        for k, t, lon, lat in conf['towns']:
            x, y = project(lon, lat)
            items.append({'key': k, 'title': t, 'cx': x, 'cy': y, 'parent': city_key})
        out[city_key] = {
            'viewBox': f'0 0 {view_w} {view_h}',
            'items': items,
        }

    out_path = os.path.join(out_dir, 'uk-towns.json')
    with open(out_path, 'w') as f:
        json.dump(out, f, separators=(',', ':'))
    n = sum(len(v['items']) for v in out.values())
    print(f'uk-towns.json   {len(out)} cities, {n} towns   {os.path.getsize(out_path)} bytes')


if __name__ == '__main__':
    src = sys.argv[1] if len(sys.argv) > 1 else '/tmp'
    out = sys.argv[2] if len(sys.argv) > 2 else os.path.join(os.path.dirname(__file__), '..', 'resources', 'data')
    os.makedirs(out, exist_ok=True)
    build_world(src, out)
    build_uk(src, out)
    # build_uk_towns kept as a separate optional dataset (point markers for
    # hand-curated town lists per major city) — the polygon flow above now
    # provides the primary drilldown via uk-<region>.json files.
    build_uk_towns(src, out)
