# Scripts

Future scripts belong here when they provide deterministic import, manifest validation, export, release packaging, or smoke-test workflows.

Scripts must be idempotent where possible, accept explicit inputs, validate before writing, support `--dry-run` for mutations, produce useful exit codes, avoid embedded credentials, and document rollback. A public page request must never invoke these scripts.

