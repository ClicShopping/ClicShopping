# CLAUDE.md
See @AGENTS.md for project instructions.

## Claude-Specific
This is a pure PHP / Composer project — there is no Node toolchain (`pnpm` is not available).
- Run PHPStan at ZERO before any commit:
- Verify a changed PHP file parses with `php -l <file>` before syncing to the live tree.
- After editing any file with a tool, deploy it to the execution server — see the `sync-to-live` skill.