# Changelog

## [Unreleased](https://codefloe.com/celema/console/compare/0.5.4...HEAD)

### Breaking Changes

- Arguments and options are declared as `__invoke()` parameters and passed to the command converted to their declared types, instead of being declared on the class and read as strings from `Args`. `#[Arg]` and `#[Opt]` now target parameters; the names derive from the parameter names in kebab-case (`$dryRun` is `--dry-run`, `$targetDir` renders as `<target-dir>`):

  ```php
  // Before
  #[Command('db:import')]
  #[Arg('file', 'The file to import')]
  #[Opt('--batch', 'Rows per batch', short: '-b', value: 'rows', default: '500')]
  #[Opt('--dry-run', 'Report without writing')]
  final class Import
  {
      public function __invoke(Args $args, Io $io): int
      {
          $file = (string) $args->positional(0);
          $batch = (int) $args->opt('--batch', '500');
          $dryRun = $args->has('--dry-run');
          // ...
      }
  }

  // After
  #[Command('db:import')]
  final class Import
  {
      public function __invoke(
          Io $io,
          #[Arg('The file to import')]
          string $file,
          #[Opt('Rows per batch', short: '-b', value: 'rows')]
          int $batch = 500,
          #[Opt('Report without writing')]
          bool $dryRun = false,
      ): int {
          // ...
      }
  }
  ```

  - Supported types are `string`, `int`, `float`, `bool`, `array`, and backed enums, also nullable. A value that does not convert, like `--batch=many`, aborts the command before it runs, as does a single-value option given more than once; previously the first value won. An enum accepts its backing values, and the help lists them as `[choices: ...]`.
  - `#[Arg]` takes only a description. An argument with a default is optional (formerly `optional: true`); an `array` argument takes the remaining positionals (formerly `variadic: true`).
  - `#[Opt]` takes a description, `short`, `value` (the help label, now defaulting to the option name), and `bare`. Every option needs a default, which the help renders as `[default: ...]` (formerly the `default:` string, which the runner never applied). A `bool` option is a flag and must default to `false`; an `array` option is repeatable, which the help marks as `[repeatable]`. `bare` replaces `optionalValue`: it is the value a bare `--name` stands for, so `#[Opt('Worker count', bare: '1')] ?int $worker = null` receives `null`, `1`, or the given count.
  - Removed `Arg::$name`, `Arg::$optional`, `Arg::$variadic`, `Opt::$long`, `Opt::$optionalValue`, `Opt::$default`, `Arg::of()`, and `Opt::of()`.
  - `__invoke()` parameters other than `Args` and `Io` must carry `#[Arg]` or `#[Opt]`. `Args` stays injectable for raw access; it holds the validated strings.
  - Commands are plain callables, so tests call them with named arguments, already converted, instead of building `Args`: `$command(io: $io, file: 'data.csv', batch: 10)`.

- Usage errors exit with code 2 instead of 1: an unknown option, a missing or malformed value, a missing or surplus argument, and an unknown or ambiguous command name. A failed run still exits with 1, so callers can tell a wrong invocation from a failed job. Scripts that check for exit code 1 after a wrong invocation need to accept 2.

- `Commands` is removed; `Runner` registers commands itself. Its constructor takes the registrations `Commands` took — instances, class-strings, and factories keyed by class-string — and defaults to none, and `Runner::add()` registers more and returns the runner: `new Runner(new Commands([new MyCommand()]))` becomes `new Runner([new MyCommand()])`. A package that returned a `Commands` collection returns a registration array instead. Reserved and duplicate names still throw on registration, and a rejected `add()` registers none of its commands.

- `Io` is final and writes through a `Terminal`. `new Io()` uses `Stdio`, the process streams, and `new Io(new Stdio('build.log', input: '/dev/tty'))` replaces the target arguments. `Buffer`, a terminal in memory, replaces `BufferedIo`: `$buffer = new Buffer("yes\n"); $command(io: new Io($buffer)); $buffer->output()`. `Buffer` is 80 columns wide unless given a `width`, while `BufferedIo` followed `COLUMNS`: a test that set the variable passes `new Buffer(width: 120)`. `Runner` takes an `Io` in place of its `output` and `errorOutput` targets: `new Runner($commands, new Io($buffer))`. The parameter is named `io`, so a call that passed an `Io` as `output:` needs the new name. The protected `stdout()`, `stderr()`, `stdin()`, `hasColorSupport()`, and target properties are gone.

- Output comes from markup templates and data arguments. `line()` and `write()` replace `echoln()` and `echo()`, and `success()`, `warn()`, and `error()` take the same template and arguments. The arguments fill the template's `sprintf()` conversions and print as plain text, so `$io->echolnErr('<red>Cannot read ' . $io->escape($path) . '</red>')` becomes `$io->error('Cannot read %s', $path)`. `info()` is gone in favor of `line()`, `echoErr()` and `echolnErr()` in favor of `$io->stderr()->write()` and `$io->stderr()->line()`, or of `warn()` and `error()` for a colored line. The message helpers no longer escape their input: their template is markup like any other. Without arguments a template is not formatted, so a `%` needs no doubling. Escaped text needs none either: `escape()` also escapes `%`, so `'<strong>' . $io->escape($file) . '</strong>: %d rows'` prints a `%` in the file name literally.
- Markup never throws: a tag without its partner — dangling, mismatched, or unclosed — prints literally instead of raising a `ValueError`. Control characters other than newlines and tabs are dropped from all output, not only from escaped text; templates keep carriage returns for progress lines. The runner reports failures and ambiguous command names through `error()`, in red. In debug mode, usage errors print no traceback.

- Prompts: `secret('Password:')` replaces `ask('Password:', hidden: true)`. `ask()` shows a non-empty default, `Name: [unnamed]`. `choice()` takes `key => label` options and returns the chosen key — for a list its index, no longer the label — and its default is a key, the first option's unless given, instead of a 1-based number: `$io->choice('Environment?', ['dev' => 'Development', 'prod' => 'Production'], default: 'prod')`. Defaults and option labels print as plain text.

- `Runner::showHelp()` and `Runner::showCommands()` are private: run the `help` and `commands` built-ins instead, e.g. `$runner->run(['run', 'commands'])`. `Args::bare()` is gone; it only served the runner's validation.

- Command names are validated: a name, like its optional prefix, consists of ASCII letters, digits, `.`, `_`, and `-` and starts with a letter or digit. Any other name, such as `cache clear` or `--version`, throws a `ValueError` when the attribute is instantiated.

- A short option name is a dash and one character, like `-b`: a longer one, such as `#[Opt(short: '-vv')]`, throws a `ValueError` when the attribute is instantiated. Give such an option its long name only.

- `Command::full()`, `Command::title()`, and `Command::of()` are internal; they serve the runner. The attribute's `name`, `prefix`, `description`, and `group` properties stay public.

- `Help` is internal: the runner answers `--help` itself, see below. A command that declared the flag only to render the screen with `new Help($io)->showFor($this)` drops both.

### Fixed

- The same file as output and error target, `new Stdio('build.log', 'build.log')`, receives both in the order written. Each target had its own stream, also in `Io` before, so the first error emptied the file and later output overwrote the errors or left NUL bytes.
- Output into a pipe whose reader quit early, like `php run commands | head -1`, raises no PHP notice. Depending on the PHP settings, the first failed write ended the process with exit code 255, or each further line printed a notice. The command now runs to its end, and the output nobody reads is dropped.
- `Stdio` writes no colors to a terminal whose `TERM` is `dumb`, such as some editor shells, which showed the escape codes as text. `FORCE_COLOR` still turns them on.
- On a terminal without `TERM`, detecting the width no longer prints the error message of `tput`.
- The C1 control characters U+0080 to U+009F are dropped from all output and from escaped text, like the other control characters. U+009B opens an escape sequence like `ESC [` on terminals that honor it, so text could still inject one.
- An exception without a message is reported by its class name instead of an empty line.
- The help overview lists each command under its own `group`. A command without a prefix ignored it and appeared under General, two prefixes with the same group got a section each, and the first command registered for a prefix set the title for all of them. Commands with the same group now share a section, which may list several prefixes and unprefixed commands, sorted by full name.
- `php run commands` lists the built-in `commands` and `help` too. They were missing from the names it prints for shell completion.
- The "Did you mean" hint for an unknown option fits what was typed. It allowed three typos whatever the length, so `--foo` suggested `--bar` and every unknown one-letter name suggested the first declared one. Now it allows one typo per three characters, counts a swapped pair of letters as one, and recognizes the beginning of a name (`--dry` for `--dry-run`), a wrong number of dashes (`-verbose`), and the built-in `--help`.
- A numeric token like `-5` is reported as an unknown option, with exit code 2, instead of failing with a `TypeError`: `Args::names()` returned such a name as an integer and now returns strings only. An unknown option on a command declaring a numeric short name, like `short: '-1'`, failed the same way while looking for a suggestion.
- When PCRE gives up on a text, for example at `pcre.backtrack_limit`, rendering it prints nothing instead of failing with a `TypeError`.

### Added

- An unknown command name gets a "Did you mean" suggestion by the rule that serves unknown options, also as the target of `help`: `php run db:imprt` reports `Command not found. Did you mean 'db:import'?`. The suggestion is one of the names `php run commands` lists, so an ambiguous bare name is never offered.
- Built-in `--help`: `php run db:import --help`, or `-h`, shows the command's help screen like `php run help db:import`; both were usage errors unless the command declared them. The runner looks for the flag before it validates the input, so it works without the required arguments. A leading flag acts as the `help` command: `php run --help` shows the overview. The built-in `help` and `commands` have a help screen as well: `php run help --help` and `php run help help` show the one of `help`, which failed with "Command not found", and `php run commands --help` and `php run help commands` the one of `commands` instead of the list. A `-h` declared as the short name of another option, like `--host`, stays that option's, and a command that declares `--help` itself still receives it; the runner then answers neither flag.
- `Io::stderr()` returns an `Io` that writes to the error output: `$io->stderr()->line('  - %s', $path)` prints a plain line there. `write()`, `rule()`, and the questions of the prompts go there as well.
- The debug traceback names the exception's class and the file and line that threw it, and lists each previous exception the same way under `Caused by:`. The trace prints without color below the red message.
- `Io::interactive()` tells whether someone can see and answer prompts, `Io::width()` the terminal width; `new Buffer(interactive: true, width: 120)` pretends to be such a terminal. `Stdio` takes `colors:` to switch colors on or off regardless of the environment, for example for a `--no-color` flag. Other devices implement the `Terminal` interface.
- Optional class resolver: `new Runner([Import::class], resolve: $container->get(...))` lazily constructs class-string registrations through any callable, without a container dependency. It also applies to classes added later; instances and explicit factories bypass it. Resolved commands are cached per registration, and help and command listings never invoke the resolver.
- `Runner::run()` takes an optional argument vector in place of `$_SERVER['argv']`, starting with the script name like it, so tests and embedding code need not change the global. Its script name reaches the help screens.
- `Celema\Console\Exception\InvalidUsage`: commands throw it for usage checks of their own, such as options that cannot be combined; the runner prints the message and exits with code 2.
- Option groups: an `__invoke()` parameter typed with a class whose constructor parameters all carry `#[Opt]` receives an instance of that class, created from the command line. Commands share options through a group, and a long option list moves out of the command's signature. The group's options are validated, converted, and listed in the help like the command's own.

## [0.5.4](https://codefloe.com/celema/console/src/tag/0.5.4) (2026-10-05)

### Changed

- Internal refactoring, stronger tests, and development-tooling changes.

### Fixed

- Hidden prompts (`ask(hidden: true)`) switch the echo off on the input stream itself instead of on the process STDIN, so an input target like `/dev/tty` is hidden even when STDIN is redirected. If the echo cannot be switched off on a terminal — say, `stty` is missing — `ask()` now throws a `RuntimeException` instead of reading the answer visibly.
- `Io::choice()` no longer calls `ctype_digit()`: the package does not require the ctype extension, so on PHP built without it every non-empty answer threw an `Error`. Accepted answers are unchanged: plain digits only.
- The help overview no longer fails with a `TypeError` when a command name or prefix is numeric, like `2026:import` or `task:2026`; such commands always ran, only the overview broke. A `0` prefix is now shown instead of dropped.
- Text passed through `Io::escape()` that ends in a backslash, like `C:\`, no longer escapes a tag placed right after it. `success()`, `warn()`, and `error()` threw a `ValueError` for such messages, as did markup composed by hand, e.g. `'<red>' . $io->escape($path) . '</red>'`. Escaped text ending in a backslash now carries an invisible marker that rendering and width measurement drop, so print it through the `Io` methods rather than elsewhere.

## [0.5.3](https://codefloe.com/celema/console/src/tag/0.5.3) (2026-07-21)

### Added

- `Io::pad()` pads text with spaces to a visible width — markup tags and multibyte characters don't count — aligned `Align::Left`, `Align::Right`, or `Align::Center`. Combined with `bg-` markup and `indent()` it composes into box-like background blocks; see the docs.
- `Io::choice()` prompts to pick from a numbered list of options and returns the chosen option; an empty answer yields the default, an invalid one asks again.
- `Table` renders minimal scc-style column output: columns size to their widest cell's visible width, align per column, and `rule()` inserts separator lines. No borders, no cell wrapping.

## [0.5.2](https://codefloe.com/celema/console/src/tag/0.5.2) (2026-07-21)

### Added

- Truecolor hex markup tags with a lowercase six-digit code: `<#ff7313>` and `<bg-#ff7313>`. Such tags previously passed through as unknown text; now they render (and broken pairs throw like any other tag).
- `Io::rule()` writes a horizontal rule spanning the terminal width, optionally capped by `$max`. The char defaults to `─`, may be a multi-char pattern, and may carry markup — the repeat count uses its visible width, so `$io->rule('<dim>─</dim>')` draws a dim line.

## [0.5.1](https://codefloe.com/celema/console/src/tag/0.5.1) (2026-07-21)

### Fixed

- `Io` and `Runner` write to `php://stdout` by default instead of `php://output`. The output-buffer stream never reports a terminal to `stream_isatty()`, so color markup was silently stripped from standard output even in interactive terminals, while stderr stayed colored. Tests capturing output via output buffering can still pass `php://output` explicitly.

## [0.5.0](https://codefloe.com/celema/console/src/tag/0.5.0) (2026-07-20)

### Breaking Changes

- Removed closure commands: `Commands::add()` no longer takes a name, description, and closure. Register an anonymous class instead — attributes work inline, so one-off commands gain declared options and arguments, validation, short-option normalization, and a full help screen, none of which closures had: `$commands->add(new #[Command('cache:clear', 'Clears the cache')] class { public function __invoke(Io $io): int { ... } });`
- Commands are strict by default: the `#[Arg]`/`#[Opt]` declarations are a command's complete interface. A command declaring no `#[Opt]`s rejects every option and one declaring no `#[Arg]`s rejects every positional — previously both were accepted unchecked. Declare everything the command reads, including options consumed by deeper machinery, and declare a variadic `#[Arg]` for open-ended positional input.
- Command names with more than one colon are rejected: the extra colon made such a name collide with the prefixed lookup of other commands, so `foo:bar` could silently execute a registered `x:foo:bar`.
- Option declarations are validated: the long name must use the `--name` form and the short name the `-s` form (no `=`, no whitespace), and `optionalValue` requires a `value` label — all rejected when the attribute is instantiated. A command declaring the same option name or short alias twice is rejected when it runs; previously the last declaration silently won.
- A lazy factory must return an instance of the class it is keyed by (subclasses included) instead of any object, so the help and validation metadata always describe the command that actually runs.

### Fixed

- Command resolution looks up exact full names before bare-name aliases: an unprefixed command now wins over a prefixed namesake instead of becoming unreachable behind a bogus ambiguity (whose message suggested the invalid `:name` form), and it shows in the `commands` listing again.
- `help <unknown>` reports the unknown target instead of blaming the `help` command itself.
- A value-requiring option repeated with a bare occurrence (`--host --host=localhost`) is rejected: every occurrence needs a value. The merged values previously hid the bare one from validation. `Args::bare()` exposes whether an option occurred without a value.
- `Io::escape()` also strips control characters — everything C0 except newline and tab, plus DEL — so untrusted text routed through it, the message helpers, or the runner's error reporting cannot inject terminal escape sequences (ESC, BEL, carriage-return spoofing). `echo()` and friends remain the raw markup path.

### Added

- `#[Arg]` supports `variadic: true` on the last argument: it accepts all remaining positionals — at least one, or any number when also `optional` — and renders as `<name>...` in the help. Declaring a further argument after a variadic one is rejected.

## [0.4.0](https://codefloe.com/celema/console/src/tag/0.4.0) (2026-07-20)

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

## [0.3.0](https://codefloe.com/celema/console/src/tag/0.3.0) (2026-07-18)

### Breaking Changes

- Rename the package from `celemas/cli` to `celema/console` and the root namespace from `Celemas\Cli` to `Celema\Console`. Update your `composer require` and every `use Celemas\Cli\...` import.
- Move the source repository to the Celema organization and update the project domain and contact email.
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

## [0.2.0](https://codefloe.com/celema/console/src/tag/0.2.0) (2026-05-10)

### Breaking Changes

- Rename package metadata, root namespace, repository URLs, homepage, and contact email to Celemas.

## [0.1.2](https://codefloe.com/celema/console/src/tag/0.1.2) (2026-04-29)

### Breaking

- Renamed the aggregate Composer script from `all` to `ci` and removed the `benchmark` and automatic config-sync scripts.

### Added

- Added `composer lint` with Mago formatting and lint checks.

### Changed

- Switched development tooling to `duon/dev` 3.x, `.dist` PHPUnit/Psalm configs, and `.coverage` coverage output.

### Fixed

- Fixed package homepage metadata to point to `duon.sh/cli`.
- Fixed command metadata handling so `0` prefixes, groups, and descriptions are not treated as missing.

## [0.1.1](https://codefloe.com/celema/console/src/tag/0.1.1) (2026-01-29)

### Changed

- Breaking: Renamed Composer scripts: `check` -> `types`, `ci` -> `all`.
- Breaking: Removed the `composer github` script; CI now runs the equivalent commands directly.
- Switched development tool dependencies to `duon/dev` and relaxed Composer stability (`minimum-stability: dev` with `prefer-stable`) to allow installing newer Psalm builds.

## [0.1.0](https://codefloe.com/celema/console/src/tag/0.1.0) (2026-01-28)

Initial version.

### Added

- Added command-specific help, a built-in `commands` command for autocomplete, and richer help/indent helpers.
- Added `info`, `success`, `warn`, `error`, and `echoln` helpers on `Command`.
- Added support for `--key=value` options, including values containing `=`.
- Added output color handling improvements, including background-only colors and conditional coloring based on terminal support.
- Added the `debug` flag on `Runner`.
- Added helpers for composing command collections and running scripts.
