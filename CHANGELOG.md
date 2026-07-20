# Changelog

## [Unreleased](https://codeberg.org/celema/console/compare/0.4.0...HEAD)

### Breaking Changes

- Removed closure commands: `Commands::add()` no longer takes a name, description, and closure. Register an anonymous class instead — attributes work inline, so one-off commands gain declared options and arguments, validation, short-option normalization, and a full help screen, none of which closures had: `$commands->add(new #[Command('cache:clear', 'Clears the cache')] class { public function __invoke(Io $io): int { ... } });`
- Commands are strict by default: the `#[Arg]`/`#[Opt]` declarations are a command's complete interface. A command declaring no `#[Opt]`s rejects every option and one declaring no `#[Arg]`s rejects every positional — previously both were accepted unchecked. Declare everything the command reads, including options consumed by deeper machinery, and declare a variadic `#[Arg]` for open-ended positional input.
- Command names with more than one colon are rejected: the extra colon made such a name collide with the prefixed lookup of other commands, so `foo:bar` could silently execute a registered `x:foo:bar`.
- Option declarations are validated: the long name must use the `--name` form and the short name the `-s` form (no `=`, no whitespace), and `optionalValue` requires a `value` label — all rejected when the attribute is instantiated. A command declaring the same option name or short alias twice is rejected when it runs; previously the last declaration silently won.
- A lazy factory must return an instance of the class it is keyed by (subclasses included) instead of any object, so the help and validation metadata always describe the command that actually runs.

### Fixed

- Command resolution looks up exact full names before bare-name aliases: an unprefixed command now wins over a prefixed namesake instead of becoming unreachable behind a bogus ambiguity (whose message suggested the invalid `:name` form), and it shows in the `commands` listing again.
- `help <unknown>` reports the unknown target instead of blaming the `help` command itself.

### Added

- `#[Arg]` supports `variadic: true` on the last argument: it accepts all remaining positionals — at least one, or any number when also `optional` — and renders as `<name>...` in the help. Declaring a further argument after a variadic one is rejected.

## [0.4.0](https://codeberg.org/celema/console/src/tag/0.4.0) (2026-07-20)

### Breaking Changes

- Removed the abstract `Command` base class. Commands are now plain classes marked with a `#[Command('prefix:name', 'description')]` attribute and invoked via `__invoke(Args $args, Io $io): int`. The `name`, `group`, `prefix`, and `description` properties are gone; an optional `group` attribute argument overrides the displayed group title, which otherwise derives from the capitalized prefix.
- Removed `Command::SUCCESS` / `Command::FAILURE`; return plain `0` / `1`. Commands must declare the return type `int` — including closure commands; the runner rejects any other declared return type (or a missing one) before invoking the command.
- The runner validates the invoked command's signature via reflection: parameters must be declared as `Args` or `Io`, each at most once. Arguments are matched by type rather than position, so both parameters are optional and their order is free — `(Io $io)`, `()`, or `(Io $io, Args $args)` all work. Untyped, nullable, union, variadic, or otherwise-typed parameters (including `Io` subclasses such as `BufferedIo`) abort before the command runs.
- Renamed `Output` to `Io` — the object reads (prompts) as well as writes — and the conventional command signature to `__invoke(Args $args, Io $io)`.
- Removed the `help()`/`helpHeader()`/`helpOption()` help API. Declare options with repeatable class-level `#[Opt]` attributes; the runner renders the help screen from the attributes.
- Moved the message helpers `info()`, `success()`, `warn()`, and `error()` from `Command` to `Io`, which commands now receive as their second `__invoke()` parameter. `Command::script()` is gone; read `$_SERVER['argv'][0]` if needed.
- `Commands::get()` was replaced by `Commands::entries()`, which returns internal registration entries consumed by the `Runner`.
- Replaced the color API with inline markup: `Io::color()` and the `$color`/`$background` parameters of the echo methods are gone. Style text with nestable tags instead — `<strong>`, `<em>`, `<dim>`, `<u>`, the ANSI colors (`<red>`, `<bright-red>`, `<gray>`) and the same names with a `bg-` prefix for backgrounds. Only exact known tags are parsed, everything else (`<info@example.com>`, generics, unknown names) passes through; `Io::escape()` renders arbitrary text literally, and the message helpers escape their input. Broken markup — a mismatched, dangling, or unclosed tag — throws a `ValueError`, also when colors are disabled, so mistakes surface in tests. The old palette names (`brown`, `purple`, `light*`) are gone, and bright colors now use the real ANSI bright codes instead of bold. The protected `Io::hasColorSupport()` now takes the stream to decide for.
- The runner now validates provided options against a command's declared `#[Opt]` attributes: an unknown option, a value on a boolean flag, or a missing required value aborts with exit code 1 (with a "Did you mean" suggestion for near misses, and a pointer to `help <command>` for an undeclared `--help`/`-h`). Commands declaring no options — including closures — keep accepting arbitrary options. A command that intercepts `--help` itself must declare it, for example `#[Opt('--help', 'Show this help', short: '-h')]`.
- Positionals are validated against the declared `#[Arg]` attributes the same way: a missing required argument or a surplus positional aborts with exit code 1. Commands declaring no `#[Arg]`s — including closures — keep accepting arbitrary positionals, so leave them undeclared for variadic input. Required arguments must be declared before optional ones; a violation is rejected when the command runs.
- Short option names declared by `#[Opt]` are normalized to their long name before command invocation. Commands should read the long name; mixed repeated forms retain their command-line order.
- Runner construction now rejects duplicate full command names and unprefixed commands named `help` or `commands`, which are reserved for the built-ins.
- `Commands` no longer accepts recursively nested registration arrays. Use one flat array or compose `Commands` instances.

### Added

- Commands can be registered as class-strings (zero-argument constructor) and as lazy factories keyed by class-string: `[Expensive::class => fn() => ...]`. Metadata is read from the attribute without instantiation, so building the help overview constructs no commands.
- Closures can be registered as lightweight commands: `$commands->add('cache:clear', 'Clears the cache', fn(Args $args, Io $io): int => ...)`.
- Added the `Help` renderer. The runner uses it for `help <command>`, and commands that intercept a `--help` flag themselves can render the same screen with `new Help($io)->showFor($this)`.
- Added `Args::names()` returning the names of all provided options.
- The first standalone `--` token ends option parsing: every later token is read as a positional, dashed or not, so values like `-5` or `--literal` can be passed as arguments. Short-option normalization and option validation stop at the separator as well.
- Added the repeatable class-level `#[Arg('name', 'description', optional: ...)]` attribute describing positional arguments: they render in the usage line (`<name>` / `[<name>]`) and as an "Arguments:" section of the command help.
- Added a `default` field on `#[Opt]`, rendered as `[default: ...]` after the option description.
- Added interactive prompts on `Io`: `ask(string $question, string $default = '', bool $hidden = false)` reads one line from the input stream (`hidden` disables terminal echo, for example for passwords), and `confirm(string $question, bool $default = false)` asks a yes/no question. Hidden answers keep their whitespace (only the trailing newline is stripped), and the saved terminal state is restored even when reading fails. `Io` takes the input target as a new third constructor argument (default `php://stdin`); `BufferedIo` accepts an `$input` string feeding the prompts, one line each.
- Added `BufferedIo` for tests: it captures regular and error output in memory (`output()` / `errorOutput()`) and disables colors, so assertions need no escape-code stripping. `Runner` now also accepts a ready `Io` instance in place of the output target string.

### Changed

- `Io::indent()` wraps on the visible width: markup tags and multibyte characters no longer count toward the line width (the package now requires `ext-mbstring`), and blank lines are no longer padded with the indent. `$max` now caps the total line width including the indent — the text wraps as if the terminal were at most that wide. The command help omits the description line for an argument or option without a description.
- Color support is decided per stream instead of once against `STDOUT`: the error helpers check the error stream, so redirecting one stream no longer discolors the other or writes escape codes into a redirected file.
- `NO_COLOR` and `FORCE_COLOR` follow the common conventions: an empty `NO_COLOR` is ignored, and `FORCE_COLOR=0` or `FORCE_COLOR=false` disables colors instead of forcing them on.

### Fixed

- On Windows, colors now actually work in cmd/PowerShell: VT100 processing is enabled on the stream instead of only queried (replacing the stale `ANSICON`/`ConEmuANSI` fallbacks), and hidden prompts and terminal-width detection no longer shell out to `stty`/`tput`, which leaked "not recognized" errors into the console. Hidden input still reads visibly on Windows.
- Redirected streams no longer receive ANSI color codes merely because `COLORTERM` is set, and terminal-width detection no longer invokes `tput` for redirected output.
- An output, error, or input target that cannot be opened now throws a `RuntimeException` naming the target on first use, instead of a `TypeError` on the first write.

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
