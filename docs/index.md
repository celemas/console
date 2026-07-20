---
title: Introduction
---

Celema Console is a command line interface helper like [Laravel's Artisan](https://laravel.com/docs/9.x/artisan) with way less magic.

## Installation

```bash
composer require celema/console
```

## Quick Start

A command is a plain class with a `#[Command]` attribute and an `__invoke()` method receiving the parsed arguments and the terminal `Io`:

```php
use Celema\Console\{Arg, Args, Command, Opt, Io};

// The first argument is the name by which the command is invoked from the
// command line. An optional `grp:` prefix namespaces the command and groups
// it in the help overview; `group` overrides the displayed group title.
#[Command('grp:mycommand', 'This is my command description', group: 'My Group')]
// Each #[Arg] describes one positional argument and each #[Opt] one
// option in the command's help text (e.g. `php run help mycommand`).
// The declarations are the command's complete interface: an unknown or
// malformed option, a missing required argument, or an undeclared
// positional aborts the command before it runs. A `variadic` #[Arg]
// takes open-ended input.
#[Arg('name', 'Who to greet', optional: true)]
#[Opt('--stuff', 'Description of --stuff', short: '-s', value: 'stuff')]
#[Opt('--conn', 'The database connection', value: 'conn', default: 'sqlite')]
#[Opt('--verbose', 'Enable verbose output', short: '-v')]
class MyCommand
{
    public function __invoke(Args $args, Io $io): int
    {
        $io->echo("Run my command\n");

        // Read options and positionals from the injected Args
        $name = $args->positional(0, 'world');   // first positional, or default
        $conn = $args->opt('--conn', 'sqlite');  // --conn=value, or default
        $verbose = $args->has('--verbose');      // boolean flag

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

The runner validates the signature of the invoked command: `__invoke()` must declare the return type `int` — the exit code — and may declare any subset of `Args` and `Io` parameters in any order. They are matched by declared type, and no other parameters are allowed.

### Io Methods

- `echo(string $text)` - Output text, rendering inline markup
- `ask(string $question, string $default = '', bool $hidden = false)` - Prompt for one line of input; `hidden` turns off terminal echo, e.g. for passwords
- `confirm(string $question, bool $default = false)` - Ask a yes/no question, rendered as `[y/N]` or `[Y/n]`
- `echoln(string $text)` - Output text with newline, rendering inline markup
- `info(string $message)` - Output an informational message
- `success(string $message)` - Output a success message (green)
- `warn(string $message)` - Output a warning message (yellow, to STDERR)
- `error(string $message)` - Output an error message (red, to STDERR)
- `escape(string $text)` - Escape markup tags so the text prints literally
- `indent(string $text, int $indent, ?int $max = null)` Indent and wrap text on its visible width; `max` caps the total line width, indent included

The constructor takes the output, error, and input targets (`php://output`, `php://stderr`, and `php://stdin` by default). A target that cannot be opened raises a `RuntimeException` on first use.

### Prompts

`Io` also reads: `ask()` prompts for one line of input, `confirm()` for a yes/no answer.

```php
public function __invoke(Args $args, Io $io): int
{
    $name = $io->ask('Migration name:', default: 'unnamed');
    $password = $io->ask('Password:', hidden: true);

    if (!$io->confirm('Apply the migrations?')) {
        return 1;
    }

    // ...
}
```

- An empty answer (or end of input) yields the default.
- `hidden` disables terminal echo while typing — for passwords — and keeps the answer's whitespace; only the trailing newline is stripped. The previous terminal state is restored afterwards, also when reading fails. On Windows, or without a terminal (piped input, tests), the line is simply read as is, visibly.
- `confirm()` renders the default as `[y/N]` or `[Y/n]`; an answer starting with `y`/`Y` means yes, an empty one means the default, anything else no.
- The input stream is the third `Io` constructor argument, `php://stdin` by default.

### Testing Commands

`BufferedIo` captures both streams in memory and disables colors, so assertions need no escape-code stripping. Its constructor accepts prompt answers, one line each:

```php
use Celema\Console\BufferedIo;

$io = new BufferedIo("yes\n");
$exitCode = new MyCommand()($args, $io);

$this->assertSame(0, $exitCode);
$this->assertStringContainsString('done', $io->output());
$this->assertSame('', $io->errorOutput());
```

The `Runner` also accepts a ready `Io` instance in place of its output target string, so full runs can be captured the same way: `new Runner($commands, $io)`.

### Markup

The echo methods render inline markup:

```php
$io->echoln('Made <strong>bold</strong>, <green>green</green>, and <u>underlined</u>');
```

- Style tags: `<strong>`, `<em>`, `<dim>`, `<u>`
- Color tags: `<black>`, `<red>`, `<green>`, `<yellow>`, `<blue>`, `<magenta>`, `<cyan>`, `<white>`, each also as a `<bright-red>` variant, plus `<gray>` as the readable alias for `<bright-black>`
- Background tags: the same names with a `bg-` prefix — `<bg-red>`, `<bg-bright-red>`, `<bg-gray>`

Tags compose by nesting, and the innermost tag wins on conflict. Only exact known tags are parsed: `<info@example.com>`, generics, and unknown names pass through untouched, so most text needs no escaping. For text that must print literally — say, user data or exception messages — use `$io->escape()`: it escapes known tags and strips control characters (keeping newlines and tabs), so untrusted text cannot inject terminal escape sequences. Broken markup (a mismatched, dangling, or unclosed tag) throws a `ValueError`, also when colors are disabled, so mistakes surface in tests. The message helpers `info()`, `success()`, `warn()`, and `error()` escape their input and treat it as plain text.

Whether codes are actually emitted is decided per stream: a non-empty `NO_COLOR` disables colors, `FORCE_COLOR` forces them on (`FORCE_COLOR=0` or `false` forces them off), and otherwise codes are only written when the stream is a terminal. `COLORTERM` alone does not color redirected output, so redirecting one stream to a file never garbles it while the other stays colored.

### Command-Line Arguments

The Runner parses the command's arguments and passes them to `__invoke(Args $args, Io $io)`:

```bash
php run mycommand up --conn=sqlite --verbose
```

- `--key=value` sets an option; repeat the flag to collect multiple values.
- A dashed token without `=`, such as `--verbose` or `-h`, is a boolean flag.
- Every other token is a positional argument.
- The first `--` ends option parsing: every later token is a positional, dashed or not — for values like `-5` or `--literal`.

```php
$args->positional(0);            // "up" (or null / a default)
$args->positionals();            // ["up"]
$args->opt('--conn', 'pgsql');   // "sqlite" (or the default)
$args->opts('--tag');            // all values for a repeated option
$args->has('--verbose');         // true
$args->names();                  // names of all provided options
```

A positional cannot start with `-` — such a token is read as a flag. When `#[Opt]` declares a short name, the runner normalizes it to the long name before invoking the command. Repeated short and long forms retain their original order, and command code only needs to read the long name.

### Validation

The `#[Arg]` and `#[Opt]` declarations are a command's complete interface; the runner validates every invocation against them before the command runs. An unknown or undeclared option (with a "Did you mean" suggestion for near misses), a value on a boolean flag, a value-taking option without `=value`, a missing required argument, or an undeclared positional aborts with exit code 1. So a typo like `--forec` — or an option on a command that takes none — fails loudly instead of being silently ignored.

For open-ended positional input declare the last argument as variadic, e.g. `#[Arg('files', 'The files to process', variadic: true)]`: it takes all remaining positionals — at least one, or any number when also `optional`.

### Command Help

`php run help <command>` renders the description and usage line from the `#[Command]` attribute, an "Arguments:" entry per `#[Arg]` attribute, and an "Options:" entry per `#[Opt]` attribute:

```php
#[Arg('file', 'The file to process')]
// Renders "<file>" in the usage line and under "Arguments:"
#[Arg('target', 'Where the result ends up', optional: true)]
// Renders "[<target>]"
#[Opt('--stuff', 'Description of --stuff', short: '-s', value: 'stuff')]
// Renders "-s=<stuff>, --stuff=<stuff>"
#[Opt('--verbose', 'Enable verbose output', short: '-v')]
// Renders "-v, --verbose"
#[Opt('--watch', 'Optionally watch files', value: 'file', optionalValue: true)]
// Renders "--watch[=<file>]"
#[Opt('--conn', 'The connection to use', value: 'conn', default: 'sqlite')]
// Appends "[default: sqlite]" to the description
```

### Built-in Commands

- `help` - Display help for all commands or a specific command
- `commands` - List all command names (useful for shell autocomplete)

The unprefixed names `help` and `commands` are reserved, and duplicate full command names are rejected when constructing the runner.

The runner reserves no flags, so `--help`/`-h` (and every other flag) belong to your command; use `php run help <command>` for a command's help screen.

A command that wants to answer `--help` itself can render the same screen with the `Help` renderer. If the command declares `#[Opt]` attributes, `--help` must be declared too, or option validation rejects it first:

```php
use Celema\Console\{Args, Help, Opt, Io};

#[Opt('--help', 'Show this help', short: '-h')]
// ... the command's other options ...

public function __invoke(Args $args, Io $io): int
{
    if ($args->has('--help') || $args->has('-h')) {
        new Help($io)->showFor($this);

        return 0;
    }

    // ...
}
```

`showFor()` reads the `#[Command]` and `#[Opt]` attributes off the instance or class, so the flag-triggered screen cannot drift from `php run help <command>`.

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
