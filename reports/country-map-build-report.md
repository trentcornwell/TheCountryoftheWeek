# Country Map Build Report

- Total countries expected (from `data/country-index.json`): **196**
- Successfully generated: **196**
- Unresolved (no source match): **0**
- Errors (matched but failed to build/validate): **0**
- Total SVG size: **985,117 bytes** (962.0 KB)
- Average SVG size: **5,026 bytes**

## Source & license

Natural Earth 1:10m Cultural Vectors, Admin 0 Countries — public domain, no attribution required. See `MAP-SOURCES.md` for full details.

## Unresolved countries

None — every country in the manifest resolved to a Natural Earth feature.

## Countries needing special handling

Four kinds of special handling apply, each documented per-country in the table below and in more detail in `MAP-SOURCES.md`:

1. **Antimeridian unwrapping** — the country's territory crosses the 180° line and would otherwise render as a broken/scattered shape.
2. **Distant islands excluded from framing** — a whole geographic cluster of islands is kept only if including it would not expand the bounding box beyond 3.0x the main cluster's own size (see `CLUSTER_GAP_DEGREES` / `PRIMARY_CLUSTER_MAX_EXPANSION`). Nearby islands are always clustered and kept together first, so an archipelago nation (Indonesia, the Philippines, Fiji) is never reduced to a single island — only islands genuinely far from the country's main population center are dropped, and only when keeping them would shrink everything else to an unreadable speck.
3. **Source geometry repaired** — the raw Natural Earth ring self-intersects (a data quality issue, not a code bug); repaired via the standard `buffer(0)` trick rather than being dropped, which is what the pipeline did before this was caught (it silently discarded all of Egypt's mainland, keeping only a few tiny Red Sea/Delta islands).
4. **Features grown to a minimum visible size** — nations made up entirely of small atolls spread across a huge area (Marshall Islands, Micronesia, Maldives, Tuvalu, Seychelles) proportionally scale down to sub-pixel specks; any polygon smaller than 14 canvas units after scaling is grown around its own position (not moved) to stay visible — the standard cartographic minimum-symbol-size technique.

| Country | Antimeridian unwrapped | Distant islands excluded | Geometry repaired | Features grown for visibility |
| --- | --- | --- | --- | --- |
| Angola |  |  |  | 1 |
| Antigua and Barbuda |  |  |  | 1 |
| Argentina |  |  |  | 5 |
| Australia |  |  |  | 89 |
| Azerbaijan |  |  |  | 3 |
| Bahamas, The |  |  |  | 22 |
| Bahrain |  |  |  | 1 |
| Bangladesh |  |  |  | 6 |
| Belize |  |  |  | 3 |
| Brazil |  |  |  | 39 |
| Burma |  |  |  | 35 |
| Cabo Verde |  |  |  | 1 |
| Cambodia |  |  |  | 3 |
| Canada |  |  |  | 371 |
| Chile |  |  |  | 154 |
| China |  |  |  | 68 |
| Colombia |  |  |  | 10 |
| Congo, Democratic Republic of the |  |  |  | 1 |
| Costa Rica |  |  |  | 2 |
| Croatia |  |  |  | 5 |
| Cuba |  |  |  | 34 |
| Cyprus |  |  |  | 1 |
| Ecuador |  |  |  | 10 |
| Egypt |  |  | yes | 6 |
| El Salvador |  |  |  | 1 |
| Equatorial Guinea |  |  |  | 1 |
| Estonia |  |  |  | 3 |
| Fiji | yes |  |  | 37 |
| Finland |  |  |  | 39 |
| France |  | 11 |  | 6 |
| Gabon |  |  |  | 1 |
| Germany |  |  |  | 16 |
| Greece |  |  |  | 34 |
| Guyana |  |  |  | 1 |
| Honduras |  |  |  | 3 |
| Iceland |  |  |  | 4 |
| India |  |  |  | 33 |
| Indonesia |  |  |  | 234 |
| Iran |  |  |  | 10 |
| Ireland |  |  |  | 2 |
| Italy |  |  |  | 25 |
| Japan |  |  |  | 101 |
| Kazakhstan |  |  |  | 5 |
| Kiribati | yes | 32 |  | 1 |
| Korea, North |  |  |  | 8 |
| Korea, South |  |  |  | 40 |
| Madagascar |  |  |  | 1 |
| Malawi |  |  |  | 2 |
| Malaysia |  |  |  | 14 |
| Maldives |  |  |  | 176 |
| Marshall Islands |  |  |  | 19 |
| Mauritania |  |  |  | 3 |
| Mauritius |  | 2 |  |  |
| Mexico |  |  |  | 48 |
| Micronesia, Federated States of |  |  |  | 20 |
| Mozambique |  |  |  | 3 |
| Netherlands |  | 3 |  | 1 |
| New Zealand | yes |  |  | 24 |
| Nicaragua |  |  |  | 3 |
| Nigeria |  |  |  | 1 |
| Norway |  | 1 |  | 105 |
| Oman |  |  |  | 3 |
| Pakistan |  |  |  | 1 |
| Palau |  |  |  | 6 |
| Panama |  |  |  | 7 |
| Papua New Guinea |  |  |  | 40 |
| Peru |  |  |  | 4 |
| Philippines |  |  |  | 71 |
| Portugal |  | 9 |  | 6 |
| Russia | yes |  |  | 197 |
| Saudi Arabia |  |  |  | 9 |
| Seychelles |  |  |  | 24 |
| Solomon Islands |  |  |  | 34 |
| South Africa |  |  |  | 2 |
| Spain |  |  |  | 14 |
| Sudan |  |  |  | 1 |
| Sweden |  |  |  | 38 |
| Taiwan |  |  |  | 2 |
| Tajikistan |  |  |  | 1 |
| Tanzania |  |  |  | 3 |
| Thailand |  |  |  | 18 |
| Tonga |  |  |  | 7 |
| Tunisia |  |  |  | 1 |
| Turkiye |  |  |  | 3 |
| Turkmenistan |  |  |  | 1 |
| Tuvalu |  |  |  | 8 |
| United Arab Emirates |  |  |  | 4 |
| United Kingdom |  |  |  | 42 |
| United States | yes |  |  | 338 |
| Uzbekistan |  |  |  | 1 |
| Vanuatu |  |  |  | 13 |
| Venezuela |  |  |  | 24 |
| Vietnam |  |  |  | 22 |
| Yemen |  |  |  | 6 |

## Validation performed

- Every SVG parsed as well-formed XML
- Every SVG's `viewBox` confirmed to be exactly `0 0 1000 1000`
- Every SVG scanned for `<script>` tags or `on*=` event handler attributes (none found/allowed)
