# Test Strategy

The planned suite includes:

- Unit tests for schedule arithmetic, manifest validation, field sanitization, and view models.
- WordPress integration tests for post/meta registration, capabilities, queries, REST exposure, and template resolution.
- End-to-end tests for homepage, Country, archive, search, navigation, countdown enhancement, and controlled failure states.
- Automated and manual WCAG 2.2 AA checks.
- Lighthouse performance budgets on representative production-like pages.
- Deployment smoke tests that verify the expected manifest key at a supplied instant.

Fixtures must be small, deterministic, license-safe, and independent of live services. Time-dependent tests must inject a clock; they must never depend on the wall clock.

