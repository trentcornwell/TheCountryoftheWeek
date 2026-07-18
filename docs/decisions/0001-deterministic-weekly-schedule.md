# ADR 0001: Deterministic Weekly Schedule

- Status: Accepted for implementation
- Date: 2026-07-15

## Context

The publication must select one country every Sunday at midnight in New York, support arbitrary historical and future weeks, and repeat forever. A cron-mutated current-country option is vulnerable to missed jobs, and an infinite JSON schedule cannot be materialized.

## Decision

Calculate occurrences with signed local-calendar week arithmetic from the Kiribati anchor and a versioned immutable country manifest. Use floor modulus for wraparound. Store neither a mutable current-country option nor an authoritative list of generated occurrences.

Bounded JSON snapshots may be generated for auditing or external consumers, but are disposable derived artifacts.

## Consequences

- Public results remain correct even when cron does not run.
- The same inputs reproduce past and future schedules.
- Date arithmetic, DST behavior, and negative modulo require strong tests.
- The manifest cannot be reordered after activation without a versioned migration policy.
- Cache expiry/purge must respect the local Sunday boundary.

