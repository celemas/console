# Changelog

## [0.1.1](https://github.com/duoncode/cli/releases/tag/0.1.1) (2026-01-29)

### Changed

- Breaking: Renamed Composer scripts: `check` -> `types`, `ci` -> `all`.
- Breaking: Removed the `composer github` script; CI now runs the equivalent
  commands directly.
- Switched development tool dependencies to `duon/dev` and relaxed Composer
  stability (`minimum-stability: dev` with `prefer-stable`) to allow installing
  newer Psalm builds.

## [0.1.0](https://github.com/duoncode/cli/releases/tag/0.1.0) (2026-01-28)

Initial version.

### Added

- Added command-specific help, a built-in `commands` command for autocomplete,
  and richer help/indent helpers.
- Added `info`, `success`, `warn`, `error`, and `echoln` helpers on `Command`.
- Added support for `--key=value` options, including values containing `=`.
- Added output color handling improvements, including background-only colors and
  conditional coloring based on terminal support.
- Added the `debug` flag on `Runner`.
- Added helpers for composing command collections and running scripts.
