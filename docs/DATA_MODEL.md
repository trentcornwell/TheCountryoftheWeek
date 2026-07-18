# Country Content Model

> **Implementation status (2026-07-15):** The core shape matches this
> document — `country` CPT, native `register_post_meta()` for every field
> (no ACF), `continent`/`region` taxonomies, attachment relationships for
> flag/map/gallery. The one significant simplification: fields below that
> this doc specifies as structured objects (Population, Area, Imports,
> Exports, and the general "metadata envelope" with
> `{value, as_of, source_id, source_url, retrieved_at, review_status}`)
> are implemented as plain sourced text instead — e.g. `population` stores
> `"approximately 121,000 (2024 est.)"` as one string. This was a
> deliberate scope decision to ship a working editorial UI now rather than
> build the full provenance-tracking envelope; see `PROJECT.md`'s status
> section. Revisit if per-fact source/date tracking becomes a real
> editorial requirement — the field registry is centralized in
> `CPT\Country_Meta_Fields::groups()`, so migrating a field's storage shape
> is a localized change. Interesting Facts, Prayer Points, and Suggested
> Reading are implemented as simple newline-delimited text rather than
> "repeatable structured meta/blocks", which avoids needing a repeater UI.
> `Editorial lifecycle` states (imported/needs review/etc.) are not yet
> implemented as a formal workflow — content is simply WordPress draft vs.
> publish status today.

## Model boundary

Use a `country` custom post type with the human-readable Country Name as the post title, the editorial overview as the post content only if a generic overview is needed, the canonical slug as `post_name`, and the lead/flag image as the featured image only when editorially appropriate. Use registered post meta for scalar or structured fields and taxonomies only where terms provide meaningful cross-country navigation or filtering.

The custom post type should be public, queryable, revisioned, REST-visible, and governed by custom capabilities. Use `show_in_rest` for registered fields only when there is a product need. Define strict schemas, authentication, sanitization, and authorization for every writable REST field.

Do not use Advanced Custom Fields by default. Native registered meta plus a focused editor interface reduces plugin dependence and makes the contract explicit. Reconsider only if the editorial usability cost is demonstrated.

## Field recommendations

| Requested field | Storage | Suggested shape and notes |
| --- | --- | --- |
| Country Name | Core post title | Display name; required and unique within the manifest |
| Official Name | Post meta | `string`; official long-form name with source date |
| CIA Identifier | Post meta | Immutable `string`; required, unique, indexed lookup key |
| Slug | Core `post_name` | Stable public URL segment; changes require redirects |
| ISO Codes | Post meta | Object containing ISO 3166-1 alpha-2, alpha-3, and numeric codes; validate formats |
| Flag | Attachment relationship | Attachment ID plus attribution/licensing metadata; do not store raw URL only |
| Map | Attachment relationship | Attachment ID, accessible description, source, and license |
| Capital | Post meta | Structured list to allow multiple or role-specific capitals |
| Population | Post meta | Object: value, unit, estimate year, source, retrieved date |
| Area | Post meta | Object: total/land/water square kilometers, source, date |
| Government | Post meta | Structured object for government type and descriptive note |
| Head of State | Post meta | Object with name, title, as-of date, source; time-sensitive |
| Currency | Taxonomy + meta | `currency` terms for discovery; meta for ISO code, usage notes, and as-of date |
| Languages | Taxonomy + relationship meta | `language` terms; status/percentage/source need structured meta |
| Religions | Taxonomy + relationship data | `religion` terms; percentages and estimate dates are sourced structured data |
| Ethnic Groups | Post meta | Structured list with percentage, date, and source; taxonomy is rarely useful and raises normalization concerns |
| Climate | Post meta | Curated text or blocks with source attribution |
| Terrain | Post meta | Curated text or blocks with source attribution |
| Natural Resources | Taxonomy + meta | `natural_resource` terms if browse pages are planned; retain sourced narrative in meta |
| Economy | Editorial blocks/meta | Long-form reviewed content with sources; keep presentation out of raw facts |
| Imports | Post meta | Structured value, year, unit, partners/commodities, source; do not store a bare number |
| Exports | Post meta | Same contract as imports |
| History | Editorial blocks | Revisioned long-form content; citations required |
| Geography | Editorial blocks | Revisioned long-form content; may summarize structured facts |
| Culture | Editorial blocks | Revisioned long-form content; sensitivity and source review required |
| Interesting Facts | Repeatable structured meta/blocks | Ordered items, each with claim, source, and review status |
| Prayer Content | Editorial blocks/meta | Separate field with editorial and theological review; never inferred from demographics |
| Mission Information | Editorial blocks/meta | Separate field with organization/source, date, consent/sensitivity review |
| Suggested Reading | Relationship meta | Ordered list of internal post IDs or external URL/title/author/source records |
| Photo Gallery | Attachment relationships | Ordered attachment IDs; captions, alt text, credit, license, focal point |
| Featured Week | Derived, not stored as mutable meta | Compute week index and occurrence; expose through a service/view model |
| First Featured Date | Derived | Calculate first occurrence from anchor and canonical index |
| Next Featured Date | Derived | Calculate next occurrence strictly after the evaluation instant |

## Taxonomies

Recommended at launch only if their archive pages are intentionally designed:

- `region`: hierarchical and curated; CIA/ISO regional systems must not be mixed implicitly.
- `language`: non-hierarchical; canonical term identities and aliases required.
- `currency`: non-hierarchical; key by ISO 4217 where applicable.
- `religion`: hierarchical only if an editorially approved classification exists.
- `natural_resource`: non-hierarchical and optional.

Country identity, ISO code, capital, population, government, head of state, dates, percentages, and prose are not taxonomies. Avoid taxonomies merely to make fields visible in the editor.

## Metadata envelope

Time-sensitive or factual values should support:

```json
{
  "value": "example",
  "as_of": "2026-01-01",
  "source_id": "cia-world-factbook",
  "source_url": "https://example.invalid/source-page",
  "retrieved_at": "2026-07-01T14:00:00Z",
  "review_status": "reviewed"
}
```

Production schemas should use typed objects rather than applying this envelope blindly to every field. URLs above are illustrative only.

## Identity and integrity

- Use an internal immutable country UUID or manifest key in addition to the CIA identifier; external identifiers can change.
- Enforce unique manifest key, CIA identifier, ISO alpha-2/alpha-3 codes (when present), and slug in import validation.
- Keep aliases and former names in versioned data; do not overwrite history without a migration.
- Country eligibility is a product policy. Before manifest v1, document how dependencies, territories, disputed entities, and renamed entries are handled.
- Never reorder an active manifest silently: order changes alter every historical and future schedule result.

## Media and rights

Every attachment used as a flag, map, or gallery image needs creator/source, source URL, license, license URL, credit line, acquisition date, and accessibility text. An image without verified usage rights is not publishable. Alt text describes the image’s purpose in context; captions and credits are separate.

## Editorial lifecycle

Suggested states are imported, needs review, fact-checked, editorially approved, and published. WordPress revisions capture content changes, while import reports capture field-level changes. Automated imports must not overwrite reviewed prose or silently publish changed facts.

