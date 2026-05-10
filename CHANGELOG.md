# Changelog

## [Unreleased](https://github.com/celemas/cli/compare/0.2.0...HEAD)

No notable changes since the last release.

## [0.2.0](https://github.com/celemas/cli/releases/tag/0.2.0) (2026-05-10)

### Breaking Changes

- Rename package metadata, root namespace, repository URLs, homepage, and contact email to Celemas.

## [0.1.2](https://github.com/celemas/cli/releases/tag/0.1.2) (2026-04-29)

### Breaking

- Renamed the aggregate Composer script from `all` to `ci` and removed the `benchmark` and automatic config-sync scripts.

### Added

- Added `composer lint` with Mago formatting and lint checks.

### Changed

- Switched development tooling to `duon/dev` 3.x, `.dist` PHPUnit/Psalm configs, and `.coverage` coverage output.

### Fixed

- Fixed package homepage metadata to point to `duon.dev/cli`.
- Fixed command metadata handling so `0` prefixes, groups, and descriptions are not treated as missing.

## [0.1.1](https://github.com/celemas/cli/releases/tag/0.1.1) (2026-01-29)

### Changed

- Breaking: Renamed Composer scripts: `check` -> `types`, `ci` -> `all`.
- Breaking: Removed the `composer github` script; CI now runs the equivalent commands directly.
- Switched development tool dependencies to `duon/dev` and relaxed Composer stability (`minimum-stability: dev` with `prefer-stable`) to allow installing newer Psalm builds.

## [0.1.0](https://github.com/celemas/cli/releases/tag/0.1.0) (2026-01-28)

Initial version.

### Added

- Added command-specific help, a built-in `commands` command for autocomplete, and richer help/indent helpers.
- Added `info`, `success`, `warn`, `error`, and `echoln` helpers on `Command`.
- Added support for `--key=value` options, including values containing `=`.
- Added output color handling improvements, including background-only colors and conditional coloring based on terminal support.
- Added the `debug` flag on `Runner`.
- Added helpers for composing command collections and running scripts.
