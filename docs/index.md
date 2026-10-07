---
title: Introduction
---

Celema Console is a command line interface helper like [Laravel's Artisan](https://laravel.com/docs/9.x/artisan) with way less magic.

## Installation

```bash
composer require celema/console
```

## Quick Start

A command is a plain class with a `#[Command]` attribute and an `__invoke()` method. Its parameters receive the terminal `Io` and the command-line input, converted to their declared types:

```php
use Celema\Console\{Arg, Command, Opt, Io};

// The first argument is the name by which the command is invoked from the
// command line. An optional `grp:` prefix namespaces the command and groups
// it in the help overview; `group` overrides the displayed group title.
#[Command('grp:mycommand', 'This is my command description', group: 'My Group')]
class MyCommand
{
    // An #[Arg] parameter takes a positional argument, an #[Opt] parameter
    // an option; names derive from the parameter names, so `$dryRun` is
    // `--dry-run`. The help text (`php run help mycommand`) renders from
    // them. They are the command's complete interface: an unknown option,
    // a value of the wrong type, a missing required argument, or an
    // undeclared positional aborts the command before it runs.
    public function __invoke(
        Io $io,
        #[Arg('Who to greet')]
        string $name = 'world',
        #[Opt('Rows per batch', short: '-b')]
        int $batch = 500,
        #[Opt('The database connection')]
        string $conn = 'sqlite',
        #[Opt('Enable verbose output', short: '-v')]
        bool $verbose = false,
    ): int {
        $io->echo("Run my command for {$name}\n");

        // Output helpers with color support (warn/error go to STDERR)
        $io->info('Informational message');
        $io->success('Success message');
        $io->warn('Warning message');
        $io->error('Error message');

        // echoln adds a newline automatically
        $io->echoln('Message with automatic newline');

        return 0;
    }
}
```

The constructor is yours: take whatever dependencies the command needs and register an instance or a factory (see below).

## Features

### Registering Commands

`Commands` accepts instances, class-strings, and lazy factories:

```php
use Celema\Console\{Command, Commands, Io};

$commands = new Commands([
    new MyCommand(),                          // instance
    Simple::class,                            // zero-argument constructor
    Expensive::class => fn() => new Expensive($db), // lazy factory
]);

// An anonymous class as a lightweight one-off command — attributes,
// validation, and the help screen work exactly as for named classes.
$commands->add(new #[Command('cache:clear', 'Clears the cache')] class {
    public function __invoke(Io $io): int {
        // ...
        return 0;
    }
});
```

Commands carry their metadata in the `#[Command]` attribute, which is read without instantiating the class. Factories run only when their command is actually invoked — listing the help never constructs a command.

Pass a resolver to construct class-string registrations through a container or application runtime:

```php
$commands = new Commands(
    [Import::class, Cleanup::class],
    resolve: $container->get(...),
);
$commands->add(Report::class);
```

The resolver can be any callable. It receives the registered class name and must return an instance of that class or a subclass. It applies to class names passed to the constructor or added later; instances and explicit factories bypass it. Adding another `Commands` collection preserves that collection's registrations and resolver rather than applying the receiving collection's resolver.

Resolution happens only after the runner validates the invoked command's signature and input. Help and command listings do not resolve commands. Each registration caches its resolved instance, just like an explicit factory. A resolver error fails the run with exit code 1; there is no fallback to a zero-argument constructor. Without a resolver, class-string registrations still use `new $class()`.

Console has no container dependency and does not configure services or scopes. The application owns that setup. A command can receive `Io` through its constructor instead of its `__invoke()` parameters; the resolver must supply the same instance used by the runner:

```php
use Celema\Console\{Arg, Command, Commands, Io, Runner};

#[Command('greet', 'Greets a name')]
final class Greet
{
    public function __construct(private readonly Io $io) {}

    public function __invoke(#[Arg('Who to greet')] string $name): int
    {
        $this->io->success("Hello, {$name}");

        return 0;
    }
}

$io = new Io();
$commands = new Commands(
    [Greet::class],
    resolve: static fn(string $class): object => new $class($io),
);
$runner = new Runner($commands, $io);
```

With a container-backed resolver, register that same `Io` instance in the container using its own registration API.

The runner validates the signature of the invoked command: `__invoke()` must declare the return type `int` — the exit code. Parameters typed `Args` or `Io` are injected, each at most once and in any order; every other parameter must carry `#[Arg]` or `#[Opt]` or take an option group (see [Arguments and Options](#arguments-and-options)).

### Io Methods

- `echo(string $text)` - Output text, rendering inline markup
- `ask(string $question, string $default = '', bool $hidden = false)` - Prompt for one line of input; `hidden` turns off terminal echo, e.g. for passwords
- `confirm(string $question, bool $default = false)` - Ask a yes/no question, rendered as `[y/N]` or `[Y/n]`
- `echoln(string $text)` - Output text with newline, rendering inline markup
- `info(string $message)` - Output an informational message
- `success(string $message)` - Output a success message (green)
- `warn(string $message)` - Output a warning message (yellow, to STDERR)
- `error(string $message)` - Output an error message (red, to STDERR)
- `choice(string $question, array $options, int $default = 1)` - Prompt to pick from a numbered list of options
- `escape(string $text)` - Escape markup tags so the text prints literally
- `rule(string $char = '─', ?int $max = null)` - Output a horizontal rule spanning the terminal width; `max` caps it. The char may be a multi-char pattern and carry markup — the repeat count uses its visible width: `$io->rule('<dim>─</dim>')` draws a dim line
- `pad(string $text, int $width, Align $align = Align::Left)` - Pad the text with spaces to the visible width `width`; markup tags and multibyte characters don't count, wider text is returned unchanged. `Align::Left`, `Align::Right`, or `Align::Center`
- `indent(string $text, int $indent, ?int $max = null)` Indent and wrap text on its visible width; `max` caps the total line width, indent included

The constructor takes the output, error, and input targets (`php://stdout`, `php://stderr`, and `php://stdin` by default). A target that cannot be opened raises a `RuntimeException` on first use. Tests that capture output via output buffering can pass `php://output` instead; note that color detection reports no terminal for it.

### Prompts

`Io` also reads: `ask()` prompts for one line of input, `confirm()` for a yes/no answer, `choice()` for one of a numbered list.

```php
public function __invoke(Io $io): int
{
    $name = $io->ask('Migration name:', default: 'unnamed');
    $password = $io->ask('Password:', hidden: true);
    $env = $io->choice('Environment?', ['dev', 'staging', 'prod'], default: 3);

    if (!$io->confirm('Apply the migrations?')) {
        return 1;
    }

    // ...
}
```

- An empty answer (or end of input) yields the default.
- `hidden` disables terminal echo while typing — for passwords — and keeps the answer's whitespace; only the trailing newline is stripped. The previous terminal state is restored afterwards, also when reading fails. If the echo cannot be switched off on a terminal, for example without `stty`, `ask()` throws a `RuntimeException` instead of reading visibly. On Windows, or without a terminal (piped input, tests), the line is simply read as is, visibly.
- `confirm()` renders the default as `[y/N]` or `[Y/n]`; an answer starting with `y`/`Y` means yes, an empty one means the default, anything else no.
- `choice()` lists the options numbered from 1, prompts with the default number as `[1]`, and returns the chosen option (not its number). An answer that is no listed number asks again. A default out of range, or an empty option list, throws a `ValueError`.
- The input stream is the third `Io` constructor argument, `php://stdin` by default.

### Testing Commands

`BufferedIo` captures both streams in memory and disables colors, so assertions need no escape-code stripping. Its constructor accepts prompt answers, one line each:

```php
use Celema\Console\BufferedIo;

$io = new BufferedIo("yes\n");
$exitCode = new MyCommand()(io: $io, name: 'Ada', verbose: true);

$this->assertSame(0, $exitCode);
$this->assertStringContainsString('done', $io->output());
$this->assertSame('', $io->errorOutput());
```

A command is a plain callable, so a test passes its parameters by name, already converted, and leaves out those that keep their defaults. To test the command line itself — parsing, validation, and conversion — run the command through a `Runner`, which also accepts a ready `Io` instance in place of its output target string: `new Runner($commands, $io)`.

### Markup

The echo methods render inline markup:

```php
$io->echoln('Made <strong>bold</strong>, <green>green</green>, and <u>underlined</u>');
```

- Style tags: `<strong>`, `<em>`, `<dim>`, `<u>`
- Color tags: `<black>`, `<red>`, `<green>`, `<yellow>`, `<blue>`, `<magenta>`, `<cyan>`, `<white>`, each also as a `<bright-red>` variant, plus `<gray>` as the readable alias for `<bright-black>`
- Background tags: the same names with a `bg-` prefix — `<bg-red>`, `<bg-bright-red>`, `<bg-gray>`
- Hex color tags: a lowercase six-digit code — `<#ff7313>`, `<bg-#ff7313>` — emitting 24-bit truecolor

Tags compose by nesting, and the innermost tag wins on conflict. Only exact known tags are parsed: `<info@example.com>`, generics, and unknown names pass through untouched, so most text needs no escaping. For text that must print literally — say, user data or exception messages — use `$io->escape()`: it escapes known tags and strips control characters (keeping newlines and tabs), so untrusted text cannot inject terminal escape sequences. Its result is meant for the `Io` output methods: escaped text ending in a backslash carries an invisible marker so that a tag right after it, as in `'<red>' . $io->escape($path) . '</red>'`, stays a tag. Broken markup (a mismatched, dangling, or unclosed tag) throws a `ValueError`, also when colors are disabled, so mistakes surface in tests. The message helpers `info()`, `success()`, `warn()`, and `error()` escape their input and treat it as plain text.

Whether codes are actually emitted is decided per stream: a non-empty `NO_COLOR` disables colors, `FORCE_COLOR` forces them on (`FORCE_COLOR=0` or `false` forces them off), and otherwise codes are only written when the stream is a terminal. `COLORTERM` alone does not color redirected output, so redirecting one stream to a file never garbles it while the other stays colored.

### Tables

`Table` renders rows of columns aligned on their visible width — markup tags and multibyte characters don't count, so cells may carry markup:

```php
use Celema\Console\{Align, Table};

$table = new Table(align: [Align::Left, Align::Right, Align::Right]);
$table->row(['<strong>Language</strong>', '<strong>Files</strong>', '<strong>Lines</strong>']);
$table->rule();
$table->row(['PHP', '11', '1,711']);
$table->rule();
$table->row(['<em>Total</em>', '11', '1,711']);
$io->echo($table->render());
```

Columns size to their widest cell and are separated by two spaces; alignment is per column (`Align::Left` for unlisted columns), and `rule()` inserts a separator line spanning the table, with the same char handling as `Io::rule()`. A short row leaves its remaining cells empty. Cells don't wrap — a table wider than the terminal simply overflows — so keep cells short.

### Background Blocks

Box-like highlights need no box feature: pad each line to a uniform width, wrap it in a `bg-` tag (backgrounds color the padding spaces), and indent for margin. Empty lines become the vertical padding:

```php
foreach (['', '  Import failed', '  3 of 120 pages skipped', ''] as $line) {
    $io->echoln($io->indent('<bg-red>' . $io->pad($line, 44) . '</bg-red>', 2));
}
```

With colors off the block collapses to plain indented text — nothing clutters logs or redirected output (bar the padding spaces). Two rules keep the rendering intact: pad inside the tag and indent outside, so the margin stays uncolored; and tag each line separately rather than spanning one pair across lines.

### Arguments and Options

A command declares its command line with its `__invoke()` parameters: `#[Arg]` marks a positional argument, `#[Opt]` an option. The runner validates the input against them and passes the values converted to the declared types:

```php
use Celema\Console\{Arg, Command, Opt, Io};

#[Command('db:import', 'Import records from a file')]
class Import
{
    /** @param list<string> $tag */
    public function __invoke(
        Io $io,
        #[Arg('The file to import')]
        string $file,
        #[Arg('Target format')]
        Format $format = Format::Csv, // a backed enum
        #[Opt('Rows per batch', short: '-b')]
        int $batch = 500,
        #[Opt('Only these tags')]
        array $tag = [],
        #[Opt('Report without writing')]
        bool $dryRun = false,
    ): int {
        // ...
    }
}
```

```bash
php run db:import data.csv json -b=100 --tag=news --tag=events --dry-run
```

- Names derive from the parameter names in kebab-case: `$dryRun` is `--dry-run`, and `$targetDir` renders as `<target-dir>`.
- The supported types are `string`, `int`, `float`, `bool`, `array`, and backed enums, also nullable. An enum accepts its backing values. A value that does not convert, like `--batch=many` or `--format=xml`, aborts the command.
- Positionals match the arguments in declaration order. An argument with a default is optional. An `array` argument must be the last one and takes the remaining positionals as strings: at least one, or any number when it has a default.
- Every option needs a default, which the command receives when the option is absent; use a nullable type for "not given", like `?int $limit = null`. A `bool` option is a flag without value and must default to `false`. An `array` option is repeatable and collects its values as strings. Any other option takes exactly one value.
- `short` adds an alias, such as `-b`. `value` overrides the `<value>` label in the help, which defaults to the option name.
- `bare` makes the value of an option optional. With `#[Opt('Worker count', bare: '1')] ?int $worker = null`, the command receives `null` without `--worker`, `1` for a bare `--worker`, and `4` for `--worker=4`. The bare value is written as on the command line.
- A default known only at runtime, such as a port configured in the constructor, becomes a nullable parameter: `?int $port = null`, then `$port ?? $this->port`.

The command line is parsed into options and positionals:

- `--key=value` sets an option; repeat it for a repeatable option.
- A dashed token without `=`, such as `--verbose` or `-v`, is an option without a value.
- Every other token is a positional argument.
- The first `--` ends option parsing: every later token is a positional, dashed or not — for values like `-5` or `--literal`.

A positional cannot start with `-` — such a token is read as an option. Declared short names are normalized to their long names before binding.

#### Option Groups

Options shared by several commands, or too many for one signature, go into an option group: a class whose constructor parameters all carry `#[Opt]`. A command takes the group as an `__invoke()` parameter, and the runner creates it from the command line:

```php
use Celema\Console\{Command, Opt, Io};

final readonly class ServeOptions
{
    public function __construct(
        #[Opt('Host to bind to')]
        public string $host = 'localhost',
        #[Opt('Port to listen on', short: '-p')]
        public int $port = 8080,
        #[Opt('Reduce output', short: '-q')]
        public bool $quiet = false,
    ) {}
}

#[Command('serve', 'Serve the application')]
final class Serve
{
    public function __invoke(
        Io $io,
        ServeOptions $options,
        #[Opt('Open a browser')]
        bool $open = false,
    ): int {
        // ...
    }
}
```

The group's options behave like the command's own: they are validated and converted the same way, listed in the help, and share one namespace with the command's options, so a name may occur only once. Tests pass a group like any other value: `$command(io: $io, options: new ServeOptions(port: 9000))`. Groups do not nest and declare no arguments.

#### Validation

The parameters are a command's complete interface; the runner validates every invocation against them before the command runs. An unknown option (with a "Did you mean" suggestion for near misses), a value on a flag, a missing value, a repeated single-value option, a value that does not convert, a missing required argument, or an undeclared positional aborts with exit code 2. So a typo like `--forec` — or an option on a command that takes none — fails loudly instead of being silently ignored.

Exit code 2 marks every wrong invocation, also an unknown or ambiguous command name, while 1 remains the code of a failed run. Scripts and cron jobs can so tell "called wrong" from "job failed". A command reports its own usage checks the same way by throwing `InvalidUsage`:

```php
use Celema\Console\Exception\InvalidUsage;

if ($apply && $testRun) {
    throw new InvalidUsage('--apply and --test-run cannot be combined');
}
```

Declarations are checked when the command runs or renders its help: an option without a default, a flag defaulting to `true`, an unsupported type, a variadic (`...`) parameter, or an argument after the `array` argument is reported as an error with exit code 1.

#### Raw Input

A command can also declare an `Args` parameter for the parsed tokens, for example to loop over a set of flags:

```php
$args->positional(0);            // "up" (or null / a default)
$args->positionals();            // ["up"]
$args->opt('--conn', 'pgsql');   // "sqlite" (or the default)
$args->opts('--tag');            // all values for a repeated option
$args->has('--verbose');         // true
$args->names();                  // names of all provided options
```

`Args` holds the raw strings after validation, with short names normalized to long ones.

### Command Help

`php run help <command>` renders the description and usage line from the `#[Command]` attribute, an "Arguments:" entry per `#[Arg]` parameter, and an "Options:" entry per `#[Opt]` parameter:

- `#[Arg] string $file` renders `<file>` in the usage line and under "Arguments:". With a default, it renders `[<file>]`; as an `array`, `<file>...`.
- `#[Opt(short: '-s')] string $stuff = ''` renders `-s=<stuff>, --stuff=<stuff>`; `value: 'path'` changes the label to `<path>`.
- `#[Opt(short: '-v')] bool $verbose = false` renders `-v, --verbose`.
- `#[Opt(bare: '.')] string $watch = ''` renders `--watch[=<watch>]`.

The descriptions are followed by `[choices: csv, json]` for an enum and `[default: sqlite]` for a string, number, or enum default. `null`, `false`, empty strings, and arrays render no default.

### Built-in Commands

- `help` - Display help for all commands or a specific command
- `commands` - List all command names (useful for shell autocomplete)

The unprefixed names `help` and `commands` are reserved, and duplicate full command names are rejected when constructing the runner.

The runner reserves no flags, so `--help`/`-h` (and every other flag) belong to your command; use `php run help <command>` for a command's help screen.

A command that wants to answer `--help` itself can render the same screen with the `Help` renderer. It declares the flag like any other option, or validation rejects it first:

```php
use Celema\Console\{Help, Opt, Io};

public function __invoke(
    Io $io,
    #[Opt('Show this help', short: '-h')]
    bool $help = false,
    // ... the command's other parameters ...
): int {
    if ($help) {
        new Help($io)->showFor($this);

        return 0;
    }

    // ...
}
```

`showFor()` reads the `#[Command]` attribute and the `__invoke()` signature off the instance or class, so the flag-triggered screen cannot drift from `php run help <command>`.

### Debug Mode

Enable debug mode in the Runner to display full stack traces when commands throw exceptions:

```php
$runner = new Runner($commands, debug: true);
```

Create a runner script, e. g. `run.php` or simply `run`:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Celema\Console\{Runner, Commands};
use MyCommand;

$commands = new Commands([new MyCommand()]);

// Optional: enable debug mode to show stack traces on errors
$runner = new Runner($commands, debug: false);

exit($runner->run());
```

Run the command:

```bash
$ php run mycommand
Run my command

$ php run grp:mycommand
Run my command

$ php run help
Available commands:

My Group
    grp:mycommand  This is my command description

$ php run help mycommand
Help entry for my command

$ php run commands
List all available command names (useful for shell
autocomplete)
```

### Shell Completion

The `commands` built-in lists exactly the invocable names — full `prefix:name` forms, unprefixed names, and bare aliases only when they are unambiguous — one per line, so it doubles as the completion source.

Give the runner script a shebang and make it executable, so it is invoked as `./run` instead of `php run`:

```php
#!/usr/bin/env php
<?php
// ...
```

```bash
chmod +x run
```

Then register the completion in your `.zshrc`:

```zsh
_run_commands() {
    compadd -- ${(f)"$(./run commands 2>/dev/null)"}
}
compdef _run_commands run
```

Or for bash:

```bash
_run_commands() {
    COMPREPLY=($(compgen -W "$(./run commands 2>/dev/null)" -- "${COMP_WORDS[COMP_CWORD]}"))
}
complete -F _run_commands ./run
```

Candidates come live from the current project on each completion; with lazy factories the call boots the autoloader only, so it stays fast.
