# Changelog

## [Unreleased](https://codeberg.org/celemas/cli/compare/0.2.0...HEAD)

### Breaking Changes

- Commands now receive parsed arguments and must return an exit code: `run(Args $args): int`. `string` returns are gone; use the new `Command::SUCCESS` / `Command::FAILURE` constants and call `exit($runner->run())`.
- Replaced the `Opts`/`Opt` classes with a single injected `Args` object. Options use `--key=value` (repeatable); a bare `--flag` or `-h` is a boolean flag; every other token is a positional. The previous `--key value` space syntax is no longer supported.
- `Runner::run()` now returns `int` instead of `int|string`, and its constructor takes an `errorOutput` target before the `debug` flag.
- `Command::helpOption()` takes structured parts — `helpOption(string $long, string $description, string $short = '', string $value = '', bool $optionalValue = false)` — and renders the `--opt=<value>` notation itself, instead of a pre-formatted option string.
- Made `Runner::orderCommands()` private.

### Added

- First-class positional arguments via `Args::positional()` and `Args::positionals()`.

### Changed

- `warn()`, `error()`, the ambiguous-command notice, and error/traceback output now write to STDERR.
- Render the built-in `commands` and `help` under a single "General" help heading and align help columns by visible width.
- `Output` resolves the terminal width once (preferring `COLUMNS`) instead of running `tput cols` on every `indent()` call.

### Fixed

- A flag before the command name no longer swallows it; `Args` reads only the command's own arguments.
- Bound the command-name split so `foo:bar:baz` no longer mis-resolves, and bounds-checked `Args`/option value access.

## [0.2.0](https://codeberg.org/celemas/cli/src/tag/0.2.0) (2026-05-10)

### Breaking Changes

- Rename package metadata, root namespace, repository URLs, homepage, and contact email to Celemas.

## [0.1.2](https://codeberg.org/celemas/cli/src/tag/0.1.2) (2026-04-29)

### Breaking

- Renamed the aggregate Composer script from `all` to `ci` and removed the `benchmark` and automatic config-sync scripts.

### Added

- Added `composer lint` with Mago formatting and lint checks.

### Changed

- Switched development tooling to `duon/dev` 3.x, `.dist` PHPUnit/Psalm configs, and `.coverage` coverage output.

### Fixed

- Fixed package homepage metadata to point to `duon.sh/cli`.
- Fixed command metadata handling so `0` prefixes, groups, and descriptions are not treated as missing.

## [0.1.1](https://codeberg.org/celemas/cli/src/tag/0.1.1) (2026-01-29)

### Changed

- Breaking: Renamed Composer scripts: `check` -> `types`, `ci` -> `all`.
- Breaking: Removed the `composer github` script; CI now runs the equivalent commands directly.
- Switched development tool dependencies to `duon/dev` and relaxed Composer stability (`minimum-stability: dev` with `prefer-stable`) to allow installing newer Psalm builds.

## [0.1.0](https://codeberg.org/celemas/cli/src/tag/0.1.0) (2026-01-28)

Initial version.

### Added

- Added command-specific help, a built-in `commands` command for autocomplete, and richer help/indent helpers.
- Added `info`, `success`, `warn`, `error`, and `echoln` helpers on `Command`.
- Added support for `--key=value` options, including values containing `=`.
- Added output color handling improvements, including background-only colors and conditional coloring based on terminal support.
- Added the `debug` flag on `Runner`.
- Added helpers for composing command collections and running scripts.
