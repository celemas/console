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
// command line. An optional `grp:` prefix namespaces the command. The help
// overview lists it under `group`, by default the capitalized prefix or,
// without one, General.
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
        // The template is markup, the arguments are data
        $io->line('Run my command for <strong>%s</strong>', $name);

        // Styled lines; warn() and error() go to STDERR
        $io->success('Imported %d rows', 120);
        $io->warn('Skipped %s', $name);
        $io->error('Connection %s failed', $conn);

        // write() leaves out the line break
        $io->write('Working ... ');

        return 0;
    }
}
```

The constructor is yours: take whatever dependencies the command needs and register an instance or a factory (see below).

Create a runner script, e. g. `run.php` or simply `run`:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Celema\Console\Runner;

$runner = new Runner([new MyCommand()]);

exit($runner->run());
```

Run the command by its full name, or without the prefix as long as that is unambiguous:

```bash
$ php run mycommand Ada --conn=pgsql
Run my command for Ada
Imported 120 rows
Skipped Ada
Connection pgsql failed
Working ...

$ php run help
Usage:
  php run [prefix:]command [arguments]

Prefixes are optional if the command is unambiguous.

Available commands:

General
  commands       Lists all available commands
  help           Displays this overview

My Group
  grp:mycommand  This is my command description

$ php run help mycommand
Description:
  This is my command description

Usage:
  php run grp:mycommand [<name>] [options]

Arguments:
    <name>
        Who to greet [default: world]

Options:
    -b=<batch>, --batch=<batch>
        Rows per batch [default: 500]
    --conn=<conn>
        The database connection [default: sqlite]
    -v, --verbose
        Enable verbose output

$ php run commands
commands
grp:mycommand
help
mycommand
```

## Features

### Registering Commands

The `Runner` accepts instances, class-strings, and lazy factories, in its constructor or via `add()`:

```php
use Celema\Console\{Command, Io, Runner};

$runner = new Runner([
    new MyCommand(),                          // instance
    Simple::class,                            // zero-argument constructor
    Expensive::class => fn() => new Expensive($db), // lazy factory
]);

// An anonymous class as a lightweight one-off command — attributes,
// validation, and the help screen work exactly as for named classes.
$runner->add(new #[Command('cache:clear', 'Clears the cache')] class {
    public function __invoke(Io $io): int {
        // ...
        return 0;
    }
});
```

`add()` returns the runner, so calls chain. Commands carry their metadata in the `#[Command]` attribute, which is read without instantiating the class. Factories run only when their command is actually invoked — listing the help never constructs a command.

A command name consists of letters, digits, `.`, `_`, and `-` and starts with a letter or digit; an optional prefix of the same form precedes it, separated by one colon: `db:migrate`. Names are matched case-insensitively. Any other name, such as one with a space or a leading dash, throws a `ValueError`.

A package ships its command set as a registration array, with factories for commands that need its own dependencies:

```php
$runner->add([
    Migrate::class => static fn(): Migrate => new Migrate($connection),
    Rollback::class => static fn(): Rollback => new Rollback($connection),
]);
```

Pass a resolver to construct class-string registrations through a container or application runtime:

```php
$runner = new Runner(
    [Import::class, Cleanup::class],
    resolve: $container->get(...),
);
$runner->add(Report::class);
```

The resolver can be any callable. It receives the registered class name and must return an instance of that class or a subclass. It applies to class names passed to the constructor or added later; instances and explicit factories bypass it.

Resolution happens only after the runner validates the invoked command's signature and input. Help and command listings do not resolve commands. Each registration caches its resolved instance, just like an explicit factory. A resolver error fails the run with exit code 1; there is no fallback to a zero-argument constructor. Without a resolver, class-string registrations still use `new $class()`.

Console has no container dependency and does not configure services or scopes. The application owns that setup. A command can receive `Io` through its constructor instead of its `__invoke()` parameters; the resolver must supply the same instance used by the runner:

```php
use Celema\Console\{Arg, Command, Io, Runner};

#[Command('greet', 'Greets a name')]
final class Greet
{
    public function __construct(private readonly Io $io) {}

    public function __invoke(#[Arg('Who to greet')] string $name): int
    {
        $this->io->success('Hello, %s', $name);

        return 0;
    }
}

$io = new Io();
$runner = new Runner(
    [Greet::class],
    $io,
    resolve: static fn(string $class): object => new $class($io),
);
```

With a container-backed resolver, register that same `Io` instance in the container using its own registration API.

The runner validates the signature of the invoked command: `__invoke()` must declare the return type `int` — the exit code. Parameters typed `Args` or `Io` are injected, each at most once; every other parameter must carry `#[Arg]` or `#[Opt]` or take an option group (see [Arguments and Options](#arguments-and-options)). Declare `Args` and `Io` before the first parameter with a default: PHP drops a default that a required parameter follows.

### Io Methods

- `line(string $template = '', ...$args)` - Output a line; see [Output](#output)
- `write(string $template, ...$args)` - Output text without a line break
- `success(string $template, ...$args)` - Output a green line
- `warn(string $template, ...$args)` - Output a yellow line to STDERR
- `error(string $template, ...$args)` - Output a red line to STDERR
- `stderr()` - An `Io` whose methods all write to STDERR: `$io->stderr()->line(...)`
- `ask(string $question, string $default = '')` - Prompt for one line of input
- `secret(string $question)` - Prompt for input that must not show while typing, e.g. a password
- `confirm(string $question, bool $default = false)` - Ask a yes/no question, rendered as `[y/N]` or `[Y/n]`
- `choice(string $question, array $options, int|string|null $default = null)` - Prompt to pick one of the options; returns its key
- `escape(string $text)` - Escape markup tags so the text prints literally where it is concatenated into a template
- `rule(string $char = '─', ?int $max = null)` - Output a horizontal rule spanning the terminal width; `max` caps it. The char may be a multi-char pattern and carry markup — the repeat count uses its visible width: `$io->rule('<dim>─</dim>')` draws a dim line
- `pad(string $text, int $width, Align $align = Align::Left)` - Pad the text with spaces to the visible width `width`; markup tags and multibyte characters don't count, wider text is returned unchanged. `Align::Left`, `Align::Right`, or `Align::Center`
- `indent(string $text, int $indent, ?int $max = null)` Indent and wrap text on its visible width; `max` caps the total line width, indent included
- `interactive()` - Whether someone can see the prompts and answer them
- `width()` - The terminal width in columns

### Terminals

`Io` writes to and reads from a `Terminal`. `new Io()` uses `Stdio`, the process streams. `Stdio` also takes other targets, opened on first use; one that cannot be opened raises a `RuntimeException` then:

```php
use Celema\Console\{Io, Stdio};

$io = new Io(new Stdio(input: '/dev/tty'));               // prompts read from the terminal device
$io = new Io(new Stdio('build.log', 'build.log'));         // output and errors to a file
$io = new Io(new Stdio(colors: $noColor ? false : null));  // a --no-color flag
```

With the same target for the output and the errors, both go through one stream and keep their order. `Stdio` is interactive when both its input and its output are terminals. Its width comes from `COLUMNS`, else from the terminal (`tput cols`), else it is 80. Tests use `Buffer` instead (see [Testing Commands](#testing-commands)). Other devices implement the `Terminal` interface: `write()`, `read()` (one line, with `$hidden` input that should not show), `colors()`, `interactive()`, and `width()`.

### Prompts

`Io` also reads: `ask()` prompts for one line of input, `secret()` for input that must not show, `confirm()` for a yes/no answer, `choice()` for one of a numbered list.

```php
public function __invoke(Io $io): int
{
    $name = $io->ask('Migration name:', default: 'unnamed');
    $password = $io->secret('Password:');
    $env = $io->choice('Environment?', ['dev' => 'Development', 'prod' => 'Production'], default: 'prod');

    if (!$io->confirm('Apply the migrations?')) {
        return 1;
    }

    // ...
}
```

- `ask()` shows a non-empty default in brackets, `Migration name: [unnamed]`; an empty answer, or the end of input, yields it.
- `secret()` disables the terminal echo while typing — for passwords — and keeps the answer's whitespace; only the trailing newline is stripped. `Stdio` restores the previous terminal state afterwards, also when reading fails. If it cannot switch the echo off on a terminal, for example without `stty`, `secret()` throws a `RuntimeException` instead of reading visibly. On Windows, or without a terminal (piped input, tests), the line is simply read as is, visibly.
- `confirm()` renders the default as `[y/N]` or `[Y/n]`; an answer starting with `y`/`Y` means yes, an empty one means the default, anything else no.
- `choice()` lists the option labels numbered from 1 and returns the chosen option's key, the index for a list. The prompt shows the default's number, `[2]`; the default is a key, the first option's unless given. An answer that is no listed number asks again. A default that is no key, or an empty option list, throws a `ValueError`.
- Questions are markup; defaults and option labels print as plain text.
- Answers are read from the terminal's input, for `Stdio` its `input` target, `php://stdin` by default.

### Testing Commands

`Buffer` is a terminal in memory. It captures the output and the error output separately and disables colors, so assertions need no escape-code stripping:

```php
use Celema\Console\{Buffer, Io};

$buffer = new Buffer();
$exitCode = new MyCommand()(io: new Io($buffer), name: 'Ada', verbose: true);

$this->assertSame(0, $exitCode);
$this->assertStringContainsString('Run my command for Ada', $buffer->output());
$this->assertStringContainsString('Skipped Ada', $buffer->errorOutput());
```

Its constructor accepts the answers to prompts, one line each: `new Buffer("unnamed\nyes\n")`. A `Buffer` can also pretend to be an interactive terminal of a given width, or one with colors: `new Buffer(interactive: true, width: 120, colors: true)`.

A command is a plain callable, so a test passes its parameters by name, already converted, and leaves out those that keep their defaults. To test the command line itself — parsing, validation, and conversion — run the command through a `Runner`, which takes the `Io` as its second argument: `new Runner([new MyCommand()], new Io($buffer))`.

`run()` reads `$_SERVER['argv']` unless it is given an argument vector of the same shape, starting with the script name, so a test need not change the global:

```php
$buffer = new Buffer();
$exitCode = new Runner([new MyCommand()], new Io($buffer))->run(['run', 'mycommand', 'Ada', '--verbose']);
```

### Output

Every output method takes a template and its arguments. The template is markup; the arguments are data. They fill the template's `sprintf()` conversions — `%s`, `%d`, `%05.2f`, `%1$s` — and print as plain text, never as markup:

```php
$io->line('Imported <strong>%d</strong> rows from %s', $count, $path);
$io->error('Cannot read <strong>%s</strong>', $path);
$io->error('%s', $e->getMessage());
```

Arguments are strings, numbers, or `Stringable` objects; a template that names more arguments than given throws a `ValueError`. Without arguments the template is not formatted, so a `%` needs no doubling there: `$io->line('100% done')`. With arguments, write a literal `%` as `%%`, as in `sprintf()`.

Control characters other than newlines and tabs are dropped from arguments and templates, the C1 controls U+0080 to U+009F included, so no text can inject escape sequences into a UTF-8 terminal. Only templates keep carriage returns, so a progress line can redraw itself: `$io->write("\r%3d%%", $percent)`. Markup never fails: a tag without its partner prints literally. So even a message passed as the template, `$io->error($e->getMessage())`, prints safely — at worst a known tag in it renders as style. Pass untrusted text as an argument to be sure.

`success()`, `warn()`, and `error()` color the whole line; tags in the template style parts of it: `$io->success('Created <strong>%d</strong> files', $count)`.

`warn()` and `error()` write to the error output, the other methods to the standard output. `stderr()` returns an `Io` whose methods all write to the error output — for the plain lines below an error message, or for a prompt while the standard output is piped:

```php
$io->error('Cannot apply %d migrations', count($failed));

foreach ($failed as $path) {
    $io->stderr()->line('  - %s', $path);
}

$name = $io->stderr()->ask('Name:');
```

### Markup

The templates render inline markup:

```php
$io->line('Made <strong>bold</strong>, <green>green</green>, and <u>underlined</u>');
```

- Style tags: `<strong>`, `<em>`, `<dim>`, `<u>`
- Color tags: `<black>`, `<red>`, `<green>`, `<yellow>`, `<blue>`, `<magenta>`, `<cyan>`, `<white>`, each also as a `<bright-red>` variant, plus `<gray>` as the readable alias for `<bright-black>`
- Background tags: the same names with a `bg-` prefix — `<bg-red>`, `<bg-bright-red>`, `<bg-gray>`
- Hex color tags: a lowercase six-digit code — `<#ff7313>`, `<bg-#ff7313>` — emitting 24-bit truecolor

Tags compose by nesting, and the innermost tag wins on conflict. Only exact known tags are parsed: `<info@example.com>`, generics, and unknown names pass through untouched. A backslash prints a known tag literally: `\<green>`. A closing tag pairs with the innermost open tag if that has its name; a tag without a partner — dangling, mismatched, or unclosed — prints as it is.

Arguments need no escaping. Text you concatenate into a template yourself does: `$io->escape()` escapes known tags and strips control characters (keeping newlines and tabs). Its result is meant for the `Io` output methods: escaped text ending in a backslash carries an invisible marker so that a tag right after it, as in `'<red>' . $io->escape($path) . '</red>'`, stays a tag.

Whether `Stdio` emits codes is decided per stream: a non-empty `NO_COLOR` disables colors, `FORCE_COLOR` forces them on (`FORCE_COLOR=0` or `false` forces them off), and otherwise codes are only written when the stream is a terminal. `COLORTERM` alone does not color redirected output, so redirecting one stream to a file never garbles it while the other stays colored. Its `colors` argument overrides all of this for both streams.

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
$io->write($table->render());
```

Columns size to their widest cell and are separated by two spaces; alignment is per column (`Align::Left` for unlisted columns), and `rule()` inserts a separator line spanning the table, with the same char handling as `Io::rule()`. A short row leaves its remaining cells empty. Cells don't wrap — a table wider than the terminal simply overflows — so keep cells short.

### Background Blocks

Box-like highlights need no box feature: pad each line to a uniform width, wrap it in a `bg-` tag (backgrounds color the padding spaces), and indent for margin. Empty lines become the vertical padding:

```php
foreach (['', '  Import failed', '  3 of 120 pages skipped', ''] as $line) {
    $io->line($io->indent('<bg-red>' . $io->pad($line, 44) . '</bg-red>', 2));
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

- Names derive from the parameter names in kebab-case: `$dryRun` is `--dry-run`, and `$targetDir` renders as `<target-dir>`. An underscore and an acronym end a word as well: `$dry_run` is `--dry-run`, `$HTMLOnly` is `--html-only`.
- The supported types are `string`, `int`, `float`, `bool`, `array`, and backed enums, also nullable. An enum accepts its backing values. A value that does not convert, like `--batch=many` or `xml` for the format, aborts the command.
- Positionals match the arguments in declaration order. An argument with a default is optional. An `array` argument must be the last one and takes the remaining positionals as strings: at least one, or any number when it has a default.
- Every option needs a default, which the command receives when the option is absent; use a nullable type for "not given", like `?int $limit = null`. A `bool` option is a flag without value and must default to `false`. An `array` option is repeatable and collects its values as strings. Any other option takes exactly one value.
- `short` adds an alias of one character, such as `-b`. `value` overrides the `<value>` label in the help, which defaults to the option name.
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

The parameters are a command's complete interface; the runner validates every invocation against them before the command runs. An unknown option (with a "Did you mean" suggestion for near misses), a value on a flag, a missing value, a repeated single-value option, a value that does not convert, a missing required argument, or an undeclared positional aborts with exit code 2. So a typo like `--forec` — or an option on a command that takes none — fails loudly instead of being silently ignored. A near miss is a name with few typos for its length — one per three characters, a swapped pair of letters counting as one —, the beginning of a name, such as `--dry` for `--dry-run`, or a name with the wrong number of dashes.

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

`php run help <command>` and `php run <command> --help` render the description and usage line from the `#[Command]` attribute, an "Arguments:" entry per `#[Arg]` parameter, and an "Options:" entry per `#[Opt]` parameter:

- `#[Arg] string $file` renders `<file>` in the usage line and under "Arguments:". With a default, it renders `[<file>]`; as an `array`, `<file>...`.
- `#[Opt(short: '-s')] string $stuff = ''` renders `-s=<stuff>, --stuff=<stuff>`; `value: 'path'` changes the label to `<path>`.
- `#[Opt(short: '-v')] bool $verbose = false` renders `-v, --verbose`.
- `#[Opt(bare: '.')] string $watch = ''` renders `--watch[=<watch>]`.

The descriptions are followed by `[choices: csv, json]` for an enum, `[repeatable]` for an `array` option, and `[default: sqlite]` for a string, number, or enum default. `null`, `false`, empty strings, and arrays render no default.

### Built-in Commands

- `help` - Display help for all commands or a specific command
- `commands` - List all command names (useful for shell autocomplete)

The unprefixed names `help` and `commands` are reserved, and duplicate full command names are rejected: registering either throws a `ValueError`, and the rejected call registers none of its commands.

Every command also answers `--help` and `-h` with its help screen, the built-ins included: `php run db:import --help` shows the same as `php run help db:import`, and `php run help --help` the same as `php run help help`. The runner looks for the flag before it validates the input, so it works without the required arguments and next to a mistyped option. A leading flag acts as the `help` command: `php run --help` shows the overview. After a `--` separator, `--help` is a positional like any other token.

The runner only takes what a command leaves free:

- A command that declares `-h` as the short name of another option, such as `--host`, keeps it; `--help` still shows its help.
- A command that declares `--help` itself receives the flag like any other option. The runner then answers neither `--help` nor `-h`.

### Debug Mode

A command that throws fails the run with exit code 1, and the runner prints the exception's message to the error output, or its class if it has no message. In debug mode it adds the exception's class, the file and line that threw it, and the stack trace, then the same for each previous exception:

```php
$runner = new Runner([new MyCommand()], debug: true);
```

Usage errors print no trace: the input is at fault, not the code.

### Shell Completion

The `commands` built-in lists exactly the invocable names — full `prefix:name` forms, unprefixed names, the built-ins, and bare aliases only when they are unambiguous — one per line, so it doubles as the completion source.

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
