# Changelog

## [Unreleased](https://codeberg.org/celema/console/compare/0.3.0...HEAD)

### Breaking Changes

- Removed the abstract `Command` base class. Commands are now plain classes marked with a `#[Command('prefix:name', 'description')]` attribute and invoked via `__invoke(Args $args, Io $io): int`. The `name`, `group`, `prefix`, and `description` properties are gone; an optional `group` attribute argument overrides the displayed group title, which otherwise derives from the capitalized prefix.
- Removed `Command::SUCCESS` / `Command::FAILURE`; return plain `0` / `1`.
- Renamed `Output` to `Io` — the object reads (prompts) as well as writes — and the conventional command signature to `__invoke(Args $args, Io $io)`.
- Removed the `help()`/`helpHeader()`/`helpOption()` help API. Declare options with repeatable class-level `#[Opt]` attributes; the runner renders the help screen from the attributes.
- Moved the message helpers `info()`, `success()`, `warn()`, and `error()` from `Command` to `Io`, which commands now receive as their second `__invoke()` parameter. `Command::script()` is gone; read `$_SERVER['argv'][0]` if needed.
- `Commands::get()` was replaced by `Commands::entries()`, which returns internal registration entries consumed by the `Runner`.
- The runner now validates provided options against a command's declared `#[Opt]` attributes: an unknown option, a value on a boolean flag, or a missing required value aborts with exit code 1 (with a "Did you mean" suggestion for near misses, and a pointer to `help <command>` for an undeclared `--help`/`-h`). Commands declaring no options — including closures — keep accepting arbitrary options. A command that intercepts `--help` itself must declare it, for example `#[Opt('--help', 'Show this help', short: '-h')]`.

### Added

- Commands can be registered as class-strings (zero-argument constructor) and as lazy factories keyed by class-string: `[Expensive::class => fn() => ...]`. Metadata is read from the attribute without instantiation, so building the help overview constructs no commands.
- Closures can be registered as lightweight commands: `$commands->add('cache:clear', 'Clears the cache', fn(Args $args, Io $io): int => ...)`.
- Added the `Help` renderer. The runner uses it for `help <command>`, and commands that intercept a `--help` flag themselves can render the same screen with `new Help($io)->showFor($this)`.
- Added `Args::names()` returning the names of all provided options.
- Added the repeatable class-level `#[Arg('name', 'description', optional: ...)]` attribute describing positional arguments: they render in the usage line (`<name>` / `[<name>]`) and as an "Arguments:" section of the command help.
- Added a `default` field on `#[Opt]`, rendered as `[default: ...]` after the option description.
- Added interactive prompts on `Io`: `ask(string $question, string $default = '', bool $hidden = false)` reads one line from the input stream (`hidden` disables terminal echo, for example for passwords), and `confirm(string $question, bool $default = false)` asks a yes/no question. `Io` takes the input target as a new third constructor argument (default `php://stdin`); `BufferedIo` accepts an `$input` string feeding the prompts, one line each.
- Added `BufferedIo` for tests: it captures regular and error output in memory (`output()` / `errorOutput()`) and disables colors, so assertions need no escape-code stripping. `Runner` now also accepts a ready `Io` instance in place of the output target string.

## [0.3.0](https://codeberg.org/celema/console/src/tag/0.3.0) (2026-07-18)

### Breaking Changes

- Rename the package from `celemas/cli` to `celema/console` and the root namespace from `Celemas\Cli` to `Celema\Console`. Update your `composer require` and every `use Celemas\Cli\...` import.
- Move the source repository to `codeberg.org/celema/console` and update the project domain and contact email.
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

## [0.2.0](https://codeberg.org/celema/console/src/tag/0.2.0) (2026-05-10)

### Breaking Changes

- Rename package metadata, root namespace, repository URLs, homepage, and contact email to Celemas.

## [0.1.2](https://codeberg.org/celema/console/src/tag/0.1.2) (2026-04-29)

### Breaking

- Renamed the aggregate Composer script from `all` to `ci` and removed the `benchmark` and automatic config-sync scripts.

### Added

- Added `composer lint` with Mago formatting and lint checks.

### Changed

- Switched development tooling to `duon/dev` 3.x, `.dist` PHPUnit/Psalm configs, and `.coverage` coverage output.

### Fixed

- Fixed package homepage metadata to point to `duon.sh/cli`.
- Fixed command metadata handling so `0` prefixes, groups, and descriptions are not treated as missing.

## [0.1.1](https://codeberg.org/celema/console/src/tag/0.1.1) (2026-01-29)

### Changed

- Breaking: Renamed Composer scripts: `check` -> `types`, `ci` -> `all`.
- Breaking: Removed the `composer github` script; CI now runs the equivalent commands directly.
- Switched development tool dependencies to `duon/dev` and relaxed Composer stability (`minimum-stability: dev` with `prefer-stable`) to allow installing newer Psalm builds.

## [0.1.0](https://codeberg.org/celema/console/src/tag/0.1.0) (2026-01-28)

Initial version.

### Added

- Added command-specific help, a built-in `commands` command for autocomplete, and richer help/indent helpers.
- Added `info`, `success`, `warn`, `error`, and `echoln` helpers on `Command`.
- Added support for `--key=value` options, including values containing `=`.
- Added output color handling improvements, including background-only colors and conditional coloring based on terminal support.
- Added the `debug` flag on `Runner`.
- Added helpers for composing command collections and running scripts.
