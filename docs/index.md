---
title: Introduction
---

Celema Console is a command line interface helper like [Laravel's Artisan](https://laravel.com/docs/9.x/artisan) with way less magic.

## Installation

```bash
composer require celema/console
```

## Quick Start

A command is a plain class with a `#[Command]` attribute and an `__invoke()` method receiving the parsed arguments and the output:

```php
use Celema\Console\{Arg, Args, Command, Opt, Output};

// The first argument is the name by which the command is invoked from the
// command line. An optional `grp:` prefix namespaces the command and groups
// it in the help overview; `group` overrides the displayed group title.
#[Command('grp:mycommand', 'This is my command description', group: 'My Group')]
// Optional: each #[Arg] describes one positional argument and each #[Opt]
// one option in the command's help text (e.g. `php run help mycommand`).
// Declared options are also validated: an unknown or malformed option
// aborts the command before it runs.
#[Arg('name', 'Who to greet', optional: true)]
#[Opt('--stuff', 'Description of --stuff', short: '-s', value: 'stuff')]
#[Opt('--conn', 'The database connection', value: 'conn', default: 'sqlite')]
#[Opt('--verbose', 'Enable verbose output', short: '-v')]
class MyCommand
{
    public function __invoke(Args $args, Output $out): int
    {
        $out->echo("Run my command\n");

        // Read options and positionals from the injected Args
        $name = $args->positional(0, 'world');   // first positional, or default
        $conn = $args->opt('--conn', 'sqlite');  // --conn=value, or default
        $force = $args->has('--force');          // boolean flag

        // Output helpers with color support (warn/error go to STDERR)
        $out->info('Informational message');
        $out->success('Success message');
        $out->warn('Warning message');
        $out->error('Error message');

        // echoln adds a newline automatically
        $out->echoln('Message with automatic newline');

        return 0;
    }
}
```

The constructor is yours: take whatever dependencies the command needs and register an instance or a factory (see below).

## Features

### Registering Commands

`Commands` accepts instances, class-strings, lazy factories, and named closures:

```php
use Celema\Console\{Args, Commands, Output};

$commands = new Commands([
    new MyCommand(),                          // instance
    Simple::class,                            // zero-argument constructor
    Expensive::class => fn() => new Expensive($db), // lazy factory
]);

// A closure as a lightweight one-off command
$commands->add('cache:clear', 'Clears the cache', function (Args $args, Output $out): int {
    // ...
    return 0;
});
```

Class-based commands carry their metadata in the `#[Command]` attribute, which is read without instantiating the class. Factories run only when their command is actually invoked — listing the help never constructs a command.

A command returning no value (such as a `void` closure) maps to exit code 0.

### Output Methods

- `echo(string $message, string $color = '', string $background = '')` - Output text
- `ask(string $question, string $default = '', bool $hidden = false)` - Prompt for one line of input; `hidden` turns off terminal echo, e.g. for passwords
- `confirm(string $question, bool $default = false)` - Ask a yes/no question, rendered as `[y/N]` or `[Y/n]`
- `echoln(string $message, string $color = '', string $background = '')` - Output text with newline
- `info(string $message)` - Output informational message
- `success(string $message)` - Output success message (green)
- `warn(string $message)` - Output warning message (yellow, to STDERR)
- `error(string $message)` - Output error message (red, to STDERR)
- `color(string $text, string $color, string $background = '')` Return colored text
- `indent(string $text, int $indent, ?int $max = null)` Indent and wrap text

### Prompts

`Output` also reads: `ask()` prompts for one line of input, `confirm()` for a yes/no answer.

```php
public function __invoke(Args $args, Output $out): int
{
    $name = $out->ask('Migration name:', default: 'unnamed');
    $password = $out->ask('Password:', hidden: true);

    if (!$out->confirm('Apply the migrations?')) {
        return 1;
    }

    // ...
}
```

- An empty answer (or end of input) yields the default.
- `hidden` disables terminal echo while typing — for passwords. Without a terminal (piped input, tests) the line is simply read as is.
- `confirm()` renders the default as `[y/N]` or `[Y/n]`; an answer starting with `y`/`Y` means yes, an empty one means the default, anything else no.
- The input stream is the third `Output` constructor argument, `php://stdin` by default.

### Testing Commands

`BufferedOutput` captures both streams in memory and disables colors, so assertions need no escape-code stripping. Its constructor accepts prompt answers, one line each:

```php
use Celema\Console\BufferedOutput;

$out = new BufferedOutput("yes\n");
$exitCode = new MyCommand()($args, $out);

$this->assertSame(0, $exitCode);
$this->assertStringContainsString('done', $out->output());
$this->assertSame('', $out->errorOutput());
```

The `Runner` also accepts a ready `Output` instance in place of its output target string, so full runs can be captured the same way: `new Runner($commands, $out)`.

### Available Colors

Foreground: `black`, `gray`/`grey`, `red`, `lightred`, `green`, `lightgreen`, `brown`, `yellow`, `blue`, `lightblue`, `purple`, `lightpurple`, `magenta`, `lightmagenta`, `cyan`, `lightcyan`, `lightgray`/`lightgrey`, `white`

Background: `black`, `red`, `green`, `yellow`, `blue`, `purple`, `magenta`, `cyan`, `gray`/`grey`, `white`

### Command-Line Arguments

The Runner parses the command's arguments and passes them to `__invoke(Args $args, Output $out)`:

```bash
php run mycommand up --conn=sqlite --force
```

- `--key=value` sets an option; repeat the flag to collect multiple values.
- A dashed token without `=`, such as `--force` or `-h`, is a boolean flag.
- Every other token is a positional argument.

```php
$args->positional(0);            // "up" (or null / a default)
$args->positionals();            // ["up"]
$args->opt('--conn', 'pgsql');   // "sqlite" (or the default)
$args->opts('--tag');            // all values for a repeated option
$args->has('--force');           // true
$args->names();                  // names of all provided options
```

A positional cannot start with `-` — such a token is read as a flag.

### Option Validation

For a command class that declares `#[Opt]` attributes, the runner validates
the provided options before the command runs: an unknown option (with a
"Did you mean" suggestion for near misses), a value on a boolean flag, or a
value-taking option without `=value` aborts with exit code 1. So a typo like
`--forec` fails loudly instead of being silently ignored.

Commands declaring no options — including closures — accept arbitrary
options and read them from `Args` unchecked.

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

The runner reserves no flags, so `--help`/`-h` (and every other flag) belong to your command; use `php run help <command>` for a command's help screen.

A command that wants to answer `--help` itself can render the same screen with the `Help` renderer. If the command declares `#[Opt]` attributes, `--help` must be declared too, or option validation rejects it first:

```php
use Celema\Console\{Args, Help, Opt, Output};

#[Opt('--help', 'Show this help', short: '-h')]
// ... the command's other options ...

public function __invoke(Args $args, Output $out): int
{
    if ($args->has('--help') || $args->has('-h')) {
        new Help($out)->showFor($this);

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
