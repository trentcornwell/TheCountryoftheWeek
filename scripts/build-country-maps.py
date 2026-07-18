#!/usr/bin/env python3
"""
Generates a consistent, square SVG silhouette map for every country in
data/country-index.json (the site's canonical, authoritative country
list — this script never invents its own list, it only maps onto that
one).

Source data: Natural Earth's 1:10m Cultural Vectors "Admin 0 Countries"
dataset (public domain, no attribution required). See MAP-SOURCES.md
for full sourcing/licensing details.

Pipeline (per docs/spec in the task that created this script):
  1. Download/cache the Natural Earth GeoJSON.
  2. Match each website country to its Natural Earth feature by ISO
     3166-1 alpha-2 code (reusing data/factbook-media-codes.json's
     existing iso_codes mapping, with Natural Earth's ISO_A2_EH field
     as a fallback for countries where ISO_A2 is unavailable for
     political reasons — France, Norway, Kosovo, Taiwan).
  3. Extract the country's polygon geometry (handling MultiPolygon).
  4. Unwrap antimeridian-spanning countries (Russia, Fiji, Kiribati)
     so they don't render as a scattered, broken shape.
  5. Identify the "primary landmass cluster" — the polygon(s) used to
     anchor scale/centering — and exclude only islands that are BOTH
     small and far from it, so a tiny remote territory can't shrink an
     otherwise-normal-sized country to a speck. See
     PRIMARY_CLUSTER_MAX_EXPANSION and MAP-SOURCES.md for exactly which
     countries this affects and why.
  6. Project with a simple local equirectangular projection (longitude
     scaled by cos(mean latitude) of the primary cluster) so shapes
     aren't stretched, then scale so the primary cluster's longest
     dimension fills 800 of the 1000x1000 viewBox units (100 units of
     padding per side), centered at (500, 500).
  7. Simplify geometry for file size; repair (not drop) any polygon
     Shapely flags invalid, via buffer(0) (see repair_polygon() — the
     raw Natural Earth data has a self-intersecting ring in Egypt's
     mainland that would otherwise be silently discarded); grow any
     polygon smaller than MIN_FEATURE_SIZE on canvas so tiny atoll
     nations (Marshall Islands, Micronesia, Maldives, Tuvalu,
     Seychelles) don't scale down to invisible specks; write clean SVG
     (currentColor fill, evenodd fill-rule for holes/multiple islands,
     no scripts).
  8. Validate every output file.
  9. Write data/country-map-index.json and reports/country-map-build-report.md.

Safe to rerun: the Natural Earth download is cached locally and only
re-fetched if missing; every output file is fully regenerated from
source on each run (no incremental/partial state to get out of sync).

Usage:
    python scripts/build-country-maps.py
    python scripts/build-country-maps.py --force-download
"""

from __future__ import annotations

import argparse
import json
import math
import re
import sys
import urllib.request
import xml.etree.ElementTree as ET
from dataclasses import dataclass, field
from pathlib import Path

from shapely.geometry import shape as shapely_shape
from shapely.geometry import Polygon, MultiPolygon

REPO_ROOT = Path(__file__).resolve().parent.parent
CACHE_DIR = REPO_ROOT / ".cache" / "natural-earth"
NE_URL = "https://raw.githubusercontent.com/nvkelso/natural-earth-vector/master/geojson/ne_10m_admin_0_countries.geojson"
NE_CACHE_FILE = CACHE_DIR / "ne_10m_admin_0_countries.geojson"

MANIFEST_FILE = REPO_ROOT / "data" / "country-index.json"
ISO_CODES_FILE = REPO_ROOT / "data" / "factbook-media-codes.json"
MAP_INDEX_OUT = REPO_ROOT / "data" / "country-map-index.json"
REPORT_OUT = REPO_ROOT / "reports" / "country-map-build-report.md"
SOURCES_DOC = REPO_ROOT / "MAP-SOURCES.md"

MAPS_DIR = REPO_ROOT / "theme" / "country-week" / "assets" / "maps" / "countries"
PLACEHOLDER_OUT = REPO_ROOT / "theme" / "country-week" / "assets" / "maps" / "placeholder-country.svg"

# --- Tunable constants -------------------------------------------------

CANVAS = 1000
CONTENT_SIZE = 800  # longest dimension of the primary cluster fits within this
PADDING = (CANVAS - CONTENT_SIZE) / 2  # 100 units per side

# A polygon is included in the "primary cluster" (the shape used to
# anchor scale + centering) if adding it does not expand the running
# combined bounding box beyond this multiple of the anchor polygon's
# own largest dimension. Tuned empirically against the validation list
# in the task spec (Kiribati, Chile, Russia, Indonesia, USA, UK,
# Philippines, Fiji) — see MAP-SOURCES.md for the resulting per-country
# decisions.
PRIMARY_CLUSTER_MAX_EXPANSION = 3.0

# Douglas-Peucker simplification tolerance, in degrees. ~0.02 degrees
# is roughly 1-2km at the equator — enough detail to keep a country
# recognizable at icon/card size while keeping file size small.
SIMPLIFY_TOLERANCE_DEGREES = 0.02

COORD_DECIMALS = 2

# Final simplification pass, in SVG canvas units (post-projection,
# post-scale). Applied to every included polygon right before
# serializing, on top of the degree-space SIMPLIFY_TOLERANCE_DEGREES
# pass above — a country's largest landmass is already simplified in
# degree-space, but small islands (especially ones grown by
# enforce_minimum_feature_size's rounded buffer) still carry more
# points than are visible at web/icon display sizes. 1.5 units out of
# 1000 is well under one pixel at any realistic display size.
CANVAS_SIMPLIFY_TOLERANCE = 1.5

# Minimum on-canvas size (SVG units, out of the 1000x1000 viewBox) for
# any single included polygon. Nations made up entirely of small atolls
# spread across a huge area (Marshall Islands, Micronesia, Maldives,
# Tuvalu, Seychelles) proportionally scale down to sub-pixel specks —
# geographically "accurate" but rendering as a blank map. Any polygon
# smaller than this after projection/scaling is grown (via a symmetric
# buffer around its own centroid, position unchanged) up to this size,
# the standard cartographic minimum-symbol-size technique. Large
# islands/mainlands are always bigger than this and are never touched.
MIN_FEATURE_SIZE = 14.0


@dataclass
class BuildResult:
    key: str
    name: str
    status: str  # "ok" | "unresolved" | "error"
    filename: str = ""
    file_size: int = 0
    excluded_islands: int = 0
    antimeridian_unwrapped: bool = False
    repaired_invalid_geometry: bool = False
    grown_min_feature_count: int = 0
    note: str = ""


def log(msg: str) -> None:
    print(msg, flush=True)


# --- Step 1: source data -------------------------------------------------

def ensure_natural_earth_data(force_download: bool) -> dict:
    CACHE_DIR.mkdir(parents=True, exist_ok=True)

    if force_download or not NE_CACHE_FILE.exists():
        log(f"Downloading Natural Earth admin-0 countries from {NE_URL} ...")
        urllib.request.urlretrieve(NE_URL, NE_CACHE_FILE)
        log(f"Saved to {NE_CACHE_FILE} ({NE_CACHE_FILE.stat().st_size:,} bytes)")
    else:
        log(f"Using cached Natural Earth data at {NE_CACHE_FILE} "
            f"({NE_CACHE_FILE.stat().st_size:,} bytes) — pass --force-download to refresh.")

    with open(NE_CACHE_FILE, encoding="utf-8") as f:
        return json.load(f)


# --- Step 2: matching -----------------------------------------------------

def build_iso2_index(ne_data: dict) -> dict[str, list[dict]]:
    """ISO alpha-2 (upper) -> list of matching Natural Earth features."""
    index: dict[str, list[dict]] = {}
    for feat in ne_data["features"]:
        props = feat["properties"]
        for field_name in ("ISO_A2", "ISO_A2_EH"):
            code = (props.get(field_name) or "").upper()
            if code and code != "-99":
                index.setdefault(code, []).append(feat)
    return index


def match_countries(manifest: list[dict], iso_map: dict[str, str], iso2_index: dict[str, list[dict]]):
    matched: dict[str, dict] = {}
    unmatched: list[dict] = []

    for entry in manifest:
        key = entry["key"]
        iso2 = iso_map.get(key)
        candidates = iso2_index.get(iso2, []) if iso2 else []

        # De-dupe (a feature can appear under both ISO_A2 and ISO_A2_EH).
        deduped = []
        for feat in candidates:
            if feat not in deduped:
                deduped.append(feat)

        if not deduped:
            unmatched.append(entry)
            continue

        if len(deduped) > 1:
            sovereign = [f for f in deduped if f["properties"].get("TYPE") in ("Sovereign country", "Country")]
            deduped = sovereign or deduped

        matched[key] = deduped[0]

    return matched, unmatched


# --- Steps 3-6: geometry processing ---------------------------------------

def flatten_polygons(geom) -> list[Polygon]:
    if isinstance(geom, Polygon):
        return [geom]
    if isinstance(geom, MultiPolygon):
        return list(geom.geoms)
    return []


def repair_polygon(poly: Polygon) -> list[Polygon]:
    """
    Returns [poly] unchanged if already valid. If not, repairs it with
    the standard buffer(0) trick (resolves self-intersecting rings,
    which occur in the raw Natural Earth data — e.g. Egypt's mainland
    ring self-intersects at one vertex). Repairing rather than dropping
    matters: naively filtering out `not p.is_valid` polygons silently
    discarded Egypt's entire mainland (100% of its area) and rendered
    only a few tiny Red Sea/Delta islands instead. Returns [] only if
    the repair genuinely collapses to nothing.
    """
    if poly.is_valid:
        return [poly]
    fixed = poly.buffer(0)
    if fixed.is_empty:
        return []
    return [p for p in flatten_polygons(fixed) if not p.is_empty]


def unwrap_antimeridian(polygons: list[Polygon]) -> tuple[list[Polygon], bool]:
    all_lons = [x for poly in polygons for x, _y in poly.exterior.coords]

    if not all_lons or (max(all_lons) - min(all_lons)) <= 180:
        return polygons, False

    def shift_ring(coords):
        return [(x + 360 if x < 0 else x, y) for x, y in coords]

    shifted = []
    for poly in polygons:
        exterior = shift_ring(poly.exterior.coords)
        holes = [shift_ring(r.coords) for r in poly.interiors]
        shifted.append(Polygon(exterior, holes))

    return shifted, True


# Islands within this many degrees of each other's bounding box are
# considered part of the same geographic cluster (e.g. the individual
# atolls of Kiribati's Gilbert Islands group, which are each far too
# small to be their own "anchor" but clearly belong together). This is
# what CLUSTER_GAP_DEGREES tunes; PRIMARY_CLUSTER_MAX_EXPANSION (below)
# is a separate, second-stage decision about whether to also include
# an entire *other* cluster (e.g. should Hawaii's cluster join the
# contiguous-US cluster's framing?).
CLUSTER_GAP_DEGREES = 4.0


def _bbox_gap(b1: tuple, b2: tuple) -> float:
    """Straight-line gap between two bounding boxes' nearest edges (0 if
    they overlap or touch)."""
    minx1, miny1, maxx1, maxy1 = b1
    minx2, miny2, maxx2, maxy2 = b2
    dx = max(minx1 - maxx2, minx2 - maxx1, 0)
    dy = max(miny1 - maxy2, miny2 - maxy1, 0)
    return math.hypot(dx, dy)


def cluster_polygons(polygons: list[Polygon]) -> list[list[Polygon]]:
    """
    Groups polygons into geographic clusters via single-linkage
    clustering on bounding-box gap distance (union-find), so a country
    made of many small nearby islands (Kiribati's Gilbert Islands,
    Fiji, an archipelago) is recognized as one coherent group rather
    than compared island-by-island against a single "biggest" anchor —
    that per-island comparison was the original bug here: Kiribati's
    largest single atoll is tiny, so nothing else was ever "close
    enough" to it, collapsing the whole country to one atoll and then
    blowing up the resulting scale factor for every other island.
    """
    n = len(polygons)
    parent = list(range(n))

    def find(x: int) -> int:
        while parent[x] != x:
            parent[x] = parent[parent[x]]
            x = parent[x]
        return x

    def union(a: int, b: int) -> None:
        ra, rb = find(a), find(b)
        if ra != rb:
            parent[ra] = rb

    bounds = [p.bounds for p in polygons]

    for i in range(n):
        for j in range(i + 1, n):
            if _bbox_gap(bounds[i], bounds[j]) <= CLUSTER_GAP_DEGREES:
                union(i, j)

    groups: dict[int, list[Polygon]] = {}
    for i in range(n):
        groups.setdefault(find(i), []).append(polygons[i])

    return list(groups.values())


def cluster_bounds(cluster: list[Polygon]) -> tuple[float, float, float, float]:
    minx, miny, maxx, maxy = cluster[0].bounds
    for poly in cluster[1:]:
        pminx, pminy, pmaxx, pmaxy = poly.bounds
        minx, miny = min(minx, pminx), min(miny, pminy)
        maxx, maxy = max(maxx, pmaxx), max(maxy, pmaxy)
    return minx, miny, maxx, maxy


def select_primary_cluster(polygons: list[Polygon]) -> tuple[list[Polygon], int]:
    """
    Returns (included_polygons, excluded_count). Clusters nearby
    islands together first (see cluster_polygons()), picks the
    highest-total-area cluster as the anchor, then greedily folds in
    other whole clusters — by total area, largest first — as long as
    doing so doesn't expand the combined bounding box beyond
    PRIMARY_CLUSTER_MAX_EXPANSION times the anchor cluster's own
    largest dimension. Whatever is included is both the framing
    reference AND everything that gets drawn — excluded clusters are
    dropped entirely rather than drawn off-canvas, which is what
    caused the original coordinate-blowup bug (a tiny anchor's scale
    factor applied to a far-away point produces enormous numbers).
    """
    if len(polygons) <= 1:
        return polygons, 0

    clusters = cluster_polygons(polygons)

    if len(clusters) == 1:
        return clusters[0], 0

    clusters_by_area = sorted(clusters, key=lambda c: sum(p.area for p in c), reverse=True)
    anchor_cluster = clusters_by_area[0]
    minx, miny, maxx, maxy = cluster_bounds(anchor_cluster)
    included = list(anchor_cluster)
    excluded_count = 0

    for cluster in clusters_by_area[1:]:
        cminx, cminy, cmaxx, cmaxy = cluster_bounds(cluster)
        trial_minx, trial_miny = min(minx, cminx), min(miny, cminy)
        trial_maxx, trial_maxy = max(maxx, cmaxx), max(maxy, cmaxy)

        anchor_dim = max(maxx - minx, maxy - miny) or 1e-9
        trial_dim = max(trial_maxx - trial_minx, trial_maxy - trial_miny)

        if trial_dim <= anchor_dim * PRIMARY_CLUSTER_MAX_EXPANSION:
            included.extend(cluster)
            minx, miny, maxx, maxy = trial_minx, trial_miny, trial_maxx, trial_maxy
        else:
            excluded_count += len(cluster)

    return included, excluded_count


def project_point(lon: float, lat: float, cos_mean_lat: float) -> tuple[float, float]:
    """Local equirectangular projection: longitude scaled by cos(mean
    latitude) of the country's primary cluster so shapes aren't
    stretched east-west the way raw lon/lat plotting would distort
    them at higher latitudes."""
    return lon * cos_mean_lat, lat


def project_polygon_to_canvas(poly: Polygon, center_x: float, center_y: float, scale: float, cos_mean_lat: float) -> Polygon:
    def proj_ring(coords):
        pts = []
        for lon, lat in coords:
            px, py = project_point(lon, lat, cos_mean_lat)
            svg_x = CANVAS / 2 + (px - center_x) * scale
            svg_y = CANVAS / 2 - (py - center_y) * scale
            pts.append((svg_x, svg_y))
        return pts

    exterior = proj_ring(poly.exterior.coords)
    holes = [proj_ring(r.coords) for r in poly.interiors]
    return Polygon(exterior, holes)


def enforce_minimum_feature_size(poly: Polygon, min_size: float) -> tuple[list[Polygon], bool]:
    """Grows a too-small polygon (already in canvas/SVG units) by a
    symmetric buffer around its own position so its longest dimension
    reaches min_size, without moving it. No-op for anything already
    big enough. See MIN_FEATURE_SIZE for why this exists. Returns
    (polygons, grew) so callers can report which countries were affected."""
    minx, miny, maxx, maxy = poly.bounds
    span = max(maxx - minx, maxy - miny)
    if span <= 0 or span >= min_size:
        return [poly], False

    delta = (min_size - span) / 2
    # quad_segs=2 (an octagon per rounded corner, not the 8-per-quarter
    # default) — plenty smooth for a feature this small on screen, at a
    # fraction of the point count. See CANVAS_SIMPLIFY_TOLERANCE too.
    grown = poly.buffer(delta, quad_segs=2, join_style=1)
    if grown.is_empty:
        return [poly], False
    return flatten_polygons(grown) or [poly], True


def build_svg_path(canvas_polygons: list[Polygon]) -> str:
    parts = []

    def ring_to_path(coords) -> str:
        pts = [f"{x:.{COORD_DECIMALS}f},{y:.{COORD_DECIMALS}f}" for x, y in coords]
        return "M" + "L".join(pts) + "Z"

    for poly in canvas_polygons:
        parts.append(ring_to_path(poly.exterior.coords))
        for hole in poly.interiors:
            parts.append(ring_to_path(hole.coords))

    return " ".join(parts)


def build_country_svg(name: str, included_polygons: list[Polygon]) -> tuple[str, int]:
    """Returns (svg_content, grown_feature_count)."""
    minx, miny, maxx, maxy = cluster_bounds(included_polygons)

    mean_lat = (miny + maxy) / 2
    cos_mean_lat = math.cos(math.radians(mean_lat)) or 1.0

    proj_minx, _ = project_point(minx, miny, cos_mean_lat)
    proj_maxx, _ = project_point(maxx, maxy, cos_mean_lat)
    proj_width = abs(proj_maxx - proj_minx)
    proj_height = maxy - miny  # latitude is unscaled

    longest = max(proj_width, proj_height) or 1e-9
    scale = CONTENT_SIZE / longest

    center_x = (proj_minx + proj_maxx) / 2
    center_y = (miny + maxy) / 2

    canvas_polygons: list[Polygon] = []
    grown_count = 0
    for poly in included_polygons:
        canvas_poly = project_polygon_to_canvas(poly, center_x, center_y, scale, cos_mean_lat)
        grown_polys, grew = enforce_minimum_feature_size(canvas_poly, MIN_FEATURE_SIZE)
        if grew:
            grown_count += 1
        for grown in grown_polys:
            simplified_canvas = grown.simplify(CANVAS_SIMPLIFY_TOLERANCE, preserve_topology=True)
            canvas_polygons.extend(repair_polygon(simplified_canvas) if not simplified_canvas.is_empty else [])

    path_d = build_svg_path(canvas_polygons)
    safe_name = name.replace("&", "&amp;").replace('"', "&quot;")

    svg = (
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000" '
        f'role="img" aria-label="Map of {safe_name}">'
        f'<path d="{path_d}" fill="currentColor" fill-rule="evenodd"/>'
        "</svg>\n"
    )
    return svg, grown_count


# --- Validation -------------------------------------------------------

FORBIDDEN_PATTERN = re.compile(r"<script|on\w+\s*=", re.IGNORECASE)


def validate_svg(path: Path) -> list[str]:
    problems = []
    text = path.read_text(encoding="utf-8")

    if FORBIDDEN_PATTERN.search(text):
        problems.append("contains a <script> tag or on*= event handler")

    try:
        root = ET.fromstring(text)
    except ET.ParseError as exc:
        problems.append(f"not well-formed XML: {exc}")
        return problems

    viewbox = root.get("viewBox")
    if viewbox != "0 0 1000 1000":
        problems.append(f"unexpected viewBox: {viewbox!r}")

    return problems


# --- Main build -----------------------------------------------------------

def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--force-download", action="store_true", help="Re-download Natural Earth data even if cached.")
    args = parser.parse_args()

    manifest = json.loads(MANIFEST_FILE.read_text(encoding="utf-8"))["countries"]
    iso_map = json.loads(ISO_CODES_FILE.read_text(encoding="utf-8"))["iso_codes"]
    ne_data = ensure_natural_earth_data(args.force_download)

    iso2_index = build_iso2_index(ne_data)
    matched, unmatched = match_countries(manifest, iso_map, iso2_index)

    MAPS_DIR.mkdir(parents=True, exist_ok=True)

    results: list[BuildResult] = []
    map_index: dict[str, dict] = {}

    for entry in manifest:
        key = entry["key"]
        name = entry["name"]
        feat = matched.get(key)

        if feat is None:
            results.append(BuildResult(key=key, name=name, status="unresolved", note="No Natural Earth feature matched by ISO code."))
            continue

        try:
            geom = shapely_shape(feat["geometry"])
            polygons = flatten_polygons(geom)

            if not polygons:
                results.append(BuildResult(key=key, name=name, status="error", note=f"Unsupported geometry type: {geom.geom_type}"))
                continue

            polygons, unwrapped = unwrap_antimeridian(polygons)

            had_invalid = any(not p.is_valid for p in polygons)

            repaired: list[Polygon] = []
            for p in polygons:
                repaired.extend(repair_polygon(p))

            simplified = [p.simplify(SIMPLIFY_TOLERANCE_DEGREES, preserve_topology=True) for p in repaired]

            fully_valid: list[Polygon] = []
            for p in simplified:
                if p.is_empty:
                    continue
                if not p.is_valid:
                    had_invalid = True
                fully_valid.extend(repair_polygon(p))
            simplified = fully_valid

            if not simplified:
                results.append(BuildResult(key=key, name=name, status="error", note="Geometry became empty after simplification."))
                continue

            included_polygons, excluded_count = select_primary_cluster(simplified)

            svg_content, grown_count = build_country_svg(name, included_polygons)

            filename = f"{key}.svg"
            out_path = MAPS_DIR / filename
            out_path.write_text(svg_content, encoding="utf-8")

            problems = validate_svg(out_path)
            if problems:
                results.append(BuildResult(key=key, name=name, status="error", filename=filename, note="; ".join(problems)))
                continue

            props = feat["properties"]
            iso3 = props.get("ISO_A3")
            if not iso3 or iso3 == "-99":
                iso3 = props.get("ADM0_A3", "")

            map_index[name] = {
                "manifest_key": key,
                "iso2": iso_map.get(key, ""),
                "iso3": iso3,
                "source_name": props.get("NAME"),
                "source_admin": props.get("ADMIN"),
                "filename": filename,
            }

            note_parts = []
            if unwrapped:
                note_parts.append("antimeridian-unwrapped")
            if excluded_count:
                note_parts.append(f"{excluded_count} distant island polygon(s) dropped (too far from the main cluster to include without shrinking it to a speck)")
            if had_invalid:
                note_parts.append("source geometry had a self-intersecting ring, repaired via buffer(0)")
            if grown_count:
                note_parts.append(f"{grown_count} feature(s) smaller than {MIN_FEATURE_SIZE:.0f} canvas units grown to stay visible")

            results.append(BuildResult(
                key=key,
                name=name,
                status="ok",
                filename=filename,
                file_size=out_path.stat().st_size,
                excluded_islands=excluded_count,
                antimeridian_unwrapped=unwrapped,
                repaired_invalid_geometry=had_invalid,
                grown_min_feature_count=grown_count,
                note="; ".join(note_parts),
            ))

        except Exception as exc:  # noqa: BLE001 - build script, want every country's error captured, not a crash
            results.append(BuildResult(key=key, name=name, status="error", note=f"{type(exc).__name__}: {exc}"))

    for entry in unmatched:
        results.append(BuildResult(key=entry["key"], name=entry["name"], status="unresolved", note="No matching ISO code / Natural Earth feature."))

    write_placeholder()
    write_map_index(map_index)
    write_report(results, manifest)

    ok = sum(1 for r in results if r.status == "ok")
    problems = [r for r in results if r.status != "ok"]

    log(f"\n{ok}/{len(manifest)} countries generated successfully.")
    if problems:
        log(f"{len(problems)} country(ies) NOT resolved — see {REPORT_OUT.relative_to(REPO_ROOT)}:")
        for r in problems:
            log(f"  - {r.name} ({r.key}): {r.note}")
        return 1

    log("All countries resolved. See the report for special-handling notes.")
    return 0


def write_placeholder() -> None:
    PLACEHOLDER_OUT.parent.mkdir(parents=True, exist_ok=True)
    svg = (
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000" '
        'role="img" aria-label="Map unavailable">'
        '<path d="M300,300 L700,300 L700,700 L300,700 Z '
        'M420,420 L580,420 M420,500 L580,500 M420,580 L580,580" '
        'fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round"/>'
        "</svg>\n"
    )
    PLACEHOLDER_OUT.write_text(svg, encoding="utf-8")


def write_map_index(map_index: dict[str, dict]) -> None:
    out = {
        "$comment": (
            "Maps each website country (by its exact CIA World Factbook name, "
            "matching data/country-index.json) to its ISO 3166-1 codes, the "
            "matching Natural Earth admin-0 feature name, and its generated map "
            "filename. Generated by scripts/build-country-maps.py — do not "
            "hand-edit; rerun the script instead so this file and the actual "
            "SVG files never drift apart."
        ),
        "generated_from": "data/country-index.json (manifest) + Natural Earth ne_10m_admin_0_countries",
        "countries": dict(sorted(map_index.items())),
    }
    MAP_INDEX_OUT.write_text(json.dumps(out, indent=4, ensure_ascii=False) + "\n", encoding="utf-8")


def write_report(results: list[BuildResult], manifest: list[dict]) -> None:
    REPORT_OUT.parent.mkdir(parents=True, exist_ok=True)

    ok_results = [r for r in results if r.status == "ok"]
    unresolved = [r for r in results if r.status == "unresolved"]
    errors = [r for r in results if r.status == "error"]
    special = [
        r for r in ok_results
        if r.antimeridian_unwrapped or r.excluded_islands or r.repaired_invalid_geometry or r.grown_min_feature_count
    ]

    total_size = sum(r.file_size for r in ok_results)
    avg_size = total_size / len(ok_results) if ok_results else 0

    lines = []
    lines.append("# Country Map Build Report")
    lines.append("")
    lines.append(f"- Total countries expected (from `data/country-index.json`): **{len(manifest)}**")
    lines.append(f"- Successfully generated: **{len(ok_results)}**")
    lines.append(f"- Unresolved (no source match): **{len(unresolved)}**")
    lines.append(f"- Errors (matched but failed to build/validate): **{len(errors)}**")
    lines.append(f"- Total SVG size: **{total_size:,} bytes** ({total_size / 1024:.1f} KB)")
    lines.append(f"- Average SVG size: **{avg_size:,.0f} bytes**")
    lines.append("")
    lines.append("## Source & license")
    lines.append("")
    lines.append("Natural Earth 1:10m Cultural Vectors, Admin 0 Countries — public domain, "
                  "no attribution required. See `MAP-SOURCES.md` for full details.")
    lines.append("")

    if unresolved:
        lines.append("## Unresolved countries")
        lines.append("")
        for r in unresolved:
            lines.append(f"- **{r.name}** (`{r.key}`): {r.note}")
        lines.append("")
    else:
        lines.append("## Unresolved countries")
        lines.append("")
        lines.append("None — every country in the manifest resolved to a Natural Earth feature.")
        lines.append("")

    if errors:
        lines.append("## Build/validation errors")
        lines.append("")
        for r in errors:
            lines.append(f"- **{r.name}** (`{r.key}`): {r.note}")
        lines.append("")

    lines.append("## Countries needing special handling")
    lines.append("")
    lines.append("Four kinds of special handling apply, each documented per-country in the "
                 "table below and in more detail in `MAP-SOURCES.md`:")
    lines.append("")
    lines.append("1. **Antimeridian unwrapping** — the country's territory crosses the 180° "
                 "line and would otherwise render as a broken/scattered shape.")
    lines.append("2. **Distant islands excluded from framing** — a whole geographic cluster "
                 f"of islands is kept only if including it would not expand the bounding box "
                 f"beyond {PRIMARY_CLUSTER_MAX_EXPANSION}x the main cluster's own size (see "
                 "`CLUSTER_GAP_DEGREES` / `PRIMARY_CLUSTER_MAX_EXPANSION`). Nearby islands are "
                 "always clustered and kept together first, so an archipelago nation "
                 "(Indonesia, the Philippines, Fiji) is never reduced to a single island — "
                 "only islands genuinely far from the country's main population center are "
                 "dropped, and only when keeping them would shrink everything else to an "
                 "unreadable speck.")
    lines.append("3. **Source geometry repaired** — the raw Natural Earth ring self-intersects "
                 "(a data quality issue, not a code bug); repaired via the standard `buffer(0)` "
                 "trick rather than being dropped, which is what the pipeline did before this "
                 "was caught (it silently discarded all of Egypt's mainland, keeping only a "
                 "few tiny Red Sea/Delta islands).")
    lines.append("4. **Features grown to a minimum visible size** — nations made up entirely "
                 f"of small atolls spread across a huge area (Marshall Islands, Micronesia, "
                 f"Maldives, Tuvalu, Seychelles) proportionally scale down to sub-pixel specks; "
                 f"any polygon smaller than {MIN_FEATURE_SIZE:.0f} canvas units after scaling is "
                 "grown around its own position (not moved) to stay visible — the standard "
                 "cartographic minimum-symbol-size technique.")
    lines.append("")
    if special:
        lines.append("| Country | Antimeridian unwrapped | Distant islands excluded | Geometry repaired | Features grown for visibility |")
        lines.append("| --- | --- | --- | --- | --- |")
        for r in sorted(special, key=lambda r: r.name):
            lines.append(
                f"| {r.name} | {'yes' if r.antimeridian_unwrapped else ''} | "
                f"{r.excluded_islands or ''} | {'yes' if r.repaired_invalid_geometry else ''} | "
                f"{r.grown_min_feature_count or ''} |"
            )
    else:
        lines.append("None of the generated countries required special handling.")
    lines.append("")

    lines.append("## Validation performed")
    lines.append("")
    lines.append("- Every SVG parsed as well-formed XML")
    lines.append("- Every SVG's `viewBox` confirmed to be exactly `0 0 1000 1000`")
    lines.append("- Every SVG scanned for `<script>` tags or `on*=` event handler attributes (none found/allowed)")
    lines.append("")

    REPORT_OUT.write_text("\n".join(lines), encoding="utf-8")


if __name__ == "__main__":
    sys.exit(main())
