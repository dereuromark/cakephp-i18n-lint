# Changelog

## [Unreleased]

- `lint --fail-on <rules>` reports the listed warning rules as errors, e.g. `po.missing,po.obsolete`.

## [0.1.1] - 2026-10-11

- `check` and `lint` end with a `How to fix:` block, one hint per reported rule (#1).
- Suggested commands are pasteable: `vendor/bin/i18n-lint ...` including the location options of the failing run (#1).

## [0.1.0] - 2026-10-11

- Initial release: `check`, `extract`, `sync` and `lint` commands for CakePHP plugins and applications.
