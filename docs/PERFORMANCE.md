# Performance, Accessibility, and SEO Budgets

> **Implementation status (2026-07-15):** The architecture requirements are
> followed (server-rendered HTML, no page builder, no remote fonts/embeds,
> no front-end remote API calls, `srcset` via core image functions, lazy
> loading via `Hooks\Performance_Hooks`, eager/high-priority loading only
> on the hero image). Not yet done: no Lighthouse/CI measurement exists,
> so the numeric budgets below are unverified against a real build; no
> HTTP/page caching layer exists yet (every request executes the rotation
> query fresh — cheap at 196 posts, but worth adding a cache layer with
> boundary-aware TTL before production traffic); manual accessibility
> review (keyboard/screen reader) has not been performed, only semantic
> HTML structure and automated-checkable basics (labels, alt text,
> landmarks) were built in.

## Targets

On representative production-like mobile pages, Lighthouse categories must each score above 95. Core Web Vitals targets at the 75th percentile are LCP at or below 2.5 seconds, INP at or below 200 milliseconds, and CLS at or below 0.1.

Lighthouse is a lab signal, not the sole acceptance test. Measure real-user data after launch and track regressions by template.

## Initial budgets

- JavaScript: target 0 KB for basic article navigation; hard launch budget 30 KB compressed first-party JavaScript per page.
- CSS: target 25 KB compressed per page, hard launch budget 50 KB.
- Fonts: prefer system fonts; if brand fonts are approved, self-host and preload only the critical subset.
- Images: responsive AVIF/WebP with declared dimensions; hero image target below 200 KB at common mobile viewport.
- Requests: target fewer than 30 first-load requests excluding explicitly approved analytics.
- Server response: cached HTML TTFB target below 500 ms from the primary audience region.

Budgets may change through an ADR with measured evidence, not through incidental feature work.

## Architecture requirements

- Server-render HTML and use semantic landmarks and heading order.
- Use CSS for layout/interaction where possible and progressively enhance.
- Avoid page builders, general-purpose UI frameworks, icon fonts, remote fonts, and third-party embeds by default.
- Cache public HTML and deterministic queries; make cache expiry boundary-aware.
- Never call external APIs during public requests.
- Load assets only on templates that use them; version and compress assets in the release pipeline.
- Use WordPress image APIs, `srcset`, lazy loading below the fold, and eager/high-priority loading only for the true LCP image.

## Accessibility

Meet WCAG 2.2 AA: full keyboard operation, visible focus, sufficient contrast, reduced-motion support, logical reading order, descriptive controls, accessible forms, meaningful alternative text, and no information conveyed by color alone. Automated checks are necessary but manual keyboard and screen-reader review are release requirements.

## SEO

Use one canonical URL per content intent, semantic HTML, descriptive titles and meta descriptions, valid sitemap inclusion, meaningful internal links, correct archive pagination, Open Graph metadata, and appropriate Schema.org JSON-LD generated from reviewed data. Structured data must match visible content. Avoid duplicate weekly and Country content through canonical/noindex rules defined before archive implementation.

## Measurement

CI should test representative homepage, Country, and archive builds with fixed throttling and retain reports. Staging tests must run without authentication and with production caching/compression settings. Production monitoring should alert on featured-country mismatch, availability, and material Core Web Vitals regression.

