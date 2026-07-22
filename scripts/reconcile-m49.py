#!/usr/bin/env python3
"""
Reconciles the 196-country manifest (data/country-index.json) against
the UN M49 standard (cached at .cache/un-m49/UNSD-Methodology.csv) by
ISO alpha-2 code, and reports what each country's new continent/region
would become plus any manifest entries that don't cleanly match M49.

Default mode is read-only (report + reports/m49-reconciliation.json).
Pass --write to actually update data/country-index.json's continent/
region fields in place. Never touches key/name/order — see
docs/decisions/0003-multi-source-country-data-model.md for why: only
continent/region are re-sourced from M49, so the manifest's rotation
order and every downstream key-derived filename (maps, flags, post
slugs) stay untouched.

Entries in MANUAL_OVERRIDES keep their existing continent/region
exactly as-is, regardless of what M49 says (or doesn't say) about
them — a human decision, not a parsing fallback. See the ADR for why
Taiwan and Kosovo are the two entries here today.
"""
import argparse
import csv
import json
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parent.parent
M49_CSV = REPO_ROOT / ".cache" / "un-m49" / "UNSD-Methodology.csv"
MANIFEST = REPO_ROOT / "data" / "country-index.json"
ISO_CODES = REPO_ROOT / "data" / "factbook-media-codes.json"

MANIFEST_DESCRIPTION = (
    "The immutable, versioned country manifest. This is the sole source of "
    "truth for rotation ORDER - Country_Repository resolves posts by the "
    "frozen key field (stored as each posts manifest_key meta), never by "
    "live post_title, so an editorial rename can never silently reorder the "
    "schedule (see docs/decisions/0001-deterministic-weekly-schedule.md). "
    "Countries are listed alphabetically by name (unchanged since launch); "
    "continent/region come from the UN M49 standard, not any single outside "
    "organization (see docs/decisions/0003-multi-source-country-data-model.md). "
    "This file must not be reordered after it reaches production without a "
    "new manifest_version and an explicit migration ADR."
)

# manifest key -> True means "keep existing continent/region, do not
# apply an M49 value even if one existed" (neither does here).
MANUAL_OVERRIDES = {"taiwan", "kosovo"}


def fix_mojibake(s: str) -> str:
    """The source CSV has UTF-8 bytes that were interpreted as
    Latin-1/CP1252 somewhere upstream (e.g. 'RÃ©union' for 'Réunion').
    Round-tripping through latin-1 -> utf-8 repairs it; if a value has
    no such bytes the round-trip is a no-op."""
    try:
        return s.encode("latin-1").decode("utf-8")
    except (UnicodeEncodeError, UnicodeDecodeError):
        return s


def load_m49() -> dict[str, dict]:
    by_iso2: dict[str, dict] = {}
    with open(M49_CSV, encoding="utf-8-sig", newline="") as f:
        reader = csv.DictReader(f, delimiter=";")
        for row in reader:
            iso2 = (row.get("ISO-alpha2 Code") or "").strip()
            if not iso2:
                continue
            region = fix_mojibake((row.get("Region Name") or "").strip())
            subregion = fix_mojibake((row.get("Sub-region Name") or "").strip())
            intermediate = fix_mojibake((row.get("Intermediate Region Name") or "").strip())
            country_or_area = fix_mojibake((row.get("Country or Area") or "").strip())
            by_iso2[iso2] = {
                "continent": region,
                "region": intermediate or subregion,
                "un_name": country_or_area,
            }
    return by_iso2


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--write", action="store_true", help="Write updated continent/region values back to data/country-index.json.")
    args = parser.parse_args()

    manifest_json = json.loads(MANIFEST.read_text(encoding="utf-8"))
    manifest = manifest_json["countries"]
    iso_codes = json.loads(ISO_CODES.read_text(encoding="utf-8"))["iso_codes"]
    m49 = load_m49()

    matched = []
    unmatched = []
    overridden = []

    for entry in manifest:
        key = entry["key"]
        name = entry["name"]
        iso2 = iso_codes.get(key)
        m49_entry = m49.get(iso2) if iso2 else None

        if key in MANUAL_OVERRIDES:
            overridden.append({"key": key, "name": name, "iso2": iso2, "kept_continent": entry["continent"], "kept_region": entry["region"]})
            continue

        if m49_entry is None:
            unmatched.append({"key": key, "name": name, "iso2": iso2, "old_continent": entry["continent"], "old_region": entry["region"]})
            continue

        changed = (m49_entry["continent"] != entry["continent"]) or (m49_entry["region"] != entry["region"])
        matched.append({
            "key": key, "name": name, "iso2": iso2,
            "old_continent": entry["continent"], "old_region": entry["region"],
            "new_continent": m49_entry["continent"], "new_region": m49_entry["region"],
            "un_name": m49_entry["un_name"],
            "changed": changed,
        })

    if unmatched:
        print(f"REFUSING to write: {len(unmatched)} manifest countries have no M49 match and no MANUAL_OVERRIDES entry.")
        print("Add them to MANUAL_OVERRIDES (with an explicit human decision) before running --write.")
        for u in unmatched:
            print(f"  {u['name']} (key={u['key']}, iso2={u['iso2']!r})")
        if args.write:
            return

    if args.write and not unmatched:
        by_key = {m["key"]: m for m in matched}
        for entry in manifest:
            if entry["key"] in by_key:
                m = by_key[entry["key"]]
                entry["continent"] = m["new_continent"]
                entry["region"] = m["new_region"]
            # MANUAL_OVERRIDES entries: left untouched, by construction.

        manifest_json["description"] = MANIFEST_DESCRIPTION
        MANIFEST.write_text(json.dumps(manifest_json, indent=4, ensure_ascii=False) + "\n", encoding="utf-8")
        print(f"\nWrote {len(matched)} updated continent/region values to {MANIFEST.relative_to(REPO_ROOT)}")
        print(f"Kept {len(overridden)} manual override(s) unchanged: {[o['name'] for o in overridden]}")

    print(f"Manifest countries: {len(manifest)}")
    print(f"Matched to M49 by ISO2: {len(matched)}")
    print(f"NOT matched (need review): {len(unmatched)}")
    print()

    if unmatched:
        print("=== UNMATCHED (in manifest, no clean M49 ISO2 match) ===")
        for u in unmatched:
            print(f"  {u['name']:40s} key={u['key']:35s} iso2={u['iso2']!r:8s} old=({u['old_continent']}, {u['old_region']})")
        print()

    changed_continent = [m for m in matched if m["old_continent"] != m["new_continent"]]
    changed_region = [m for m in matched if m["old_region"] != m["new_region"]]

    print(f"Continent value would change for: {len(changed_continent)} countries")
    print(f"Region value would change for: {len(changed_region)} countries")
    print()

    new_continents = sorted(set(m["new_continent"] for m in matched))
    print(f"New continent set ({len(new_continents)}): {new_continents}")
    new_regions = sorted(set(m["new_region"] for m in matched))
    print(f"New region set ({len(new_regions)}): {new_regions}")

    out = REPO_ROOT / "reports" / "m49-reconciliation.json"
    out.parent.mkdir(parents=True, exist_ok=True)
    out.write_text(json.dumps({"matched": matched, "unmatched": unmatched}, indent=2, ensure_ascii=False), encoding="utf-8")
    print(f"\nFull detail written to {out.relative_to(REPO_ROOT)}")


if __name__ == "__main__":
    main()
