---
title: Introduction
---

Celema Console is a command line interface helper like [Laravel's Artisan](https://laravel.com/docs/9.x/artisan) with way less magic.

## Installation

```bash
composer require celema/console
```

## Quick Start

Create a Command:

```php
use Celema\Console\{Args, Command};

class MyCommand extends Command {
    /**
     * The name by which the MyCommand::run() method
     * is invoked from the command line.
     */
    protected string $name = 'mycommand';

    /**
     * A namespace used to distinguish equally named commands
     * from different package, e. g. `grp:mycommand`
     */
    protected string $prefix = 'grp'; // optional

    /**
     * The group name under which the command will be
     * listed in the help. Also used as prefix (lowercased)
     * if the prefix is missing
     */
    protected string $group = 'MyGroup';

    /**
     * A short description displayed in the command list
     */
    protected string $description = 'This is my command description';

    /**
     * The entry point of the command. Receives the parsed arguments and
     * returns an exit code (use the SUCCESS / FAILURE constants).
     */
    public function run(Args $args): int
    {
        $this->echo("Run my command\n");

        // Read options and positionals from the injected Args
        $name = $args->positional(0, 'world');   // first positional, or default
        $conn = $args->opt('--conn', 'sqlite');  // --conn=value, or default
        $force = $args->has('--force');          // boolean flag

        // Output helpers with color support (warn/error go to STDERR)
        $this->info("Informational message");
        $this->success("Success message");
        $this->warn("Warning message");
        $this->error("Error message");

        // echoln adds a newline automatically
        $this->echoln("Message with automatic newline");

        return self::SUCCESS;
    }

    /**
     * Optional:
     * Used to add information to the commands help text
     * (e. g. `php run help <command>`)
     */
    public function help(): void
    {
        $this->helpHeader(withOptions: true);
        // Renders "-s=<stuff>, --stuff=<stuff>"
        $this->helpOption('--stuff', 'Description of --stuff', short: '-s', value: 'stuff');
        // Renders "-v, --verbose"
        $this->helpOption('--verbose', 'Enable verbose output', short: '-v');
    }
}
```

## Features

### Output Methods

- `echo(string $message, string $color = '', string $background = '')` - Output text
- `echoln(string $message, string $color = '', string $background = '')` - Output text with newline
- `info(string $message)` - Output informational message
- `success(string $message)` - Output success message (green)
- `warn(string $message)` - Output warning message (yellow, to STDERR)
- `error(string $message)` - Output error message (red, to STDERR)
- `color(string $text, string $color, string $background = '')` Return colored text
- `indent(string $text, int $indent, ?int $max = null)` Indent and wrap text

### Available Colors

Foreground: `black`, `gray`/`grey`, `red`, `lightred`, `green`, `lightgreen`, `brown`, `yellow`, `blue`, `lightblue`, `purple`, `lightpurple`, `magenta`, `lightmagenta`, `cyan`, `lightcyan`, `lightgray`/`lightgrey`, `white`

Background: `black`, `red`, `green`, `yellow`, `blue`, `purple`, `magenta`, `cyan`, `gray`/`grey`, `white`

### Command-Line Arguments

The Runner parses the command's arguments and passes them to `run(Args $args)`:

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
```

A positional cannot start with `-` — such a token is read as a flag.

### Built-in Commands

- `help` - Display help for all commands or a specific command
- `commands` - List all command names (useful for shell autocomplete)

The runner reserves no flags, so `--help`/`-h` (and every other flag) belong to your command; use `php run help <command>` for a command's help screen.

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

MyGroup
    grp:mycommand  This is my command description

$ php run help mycommand
Help entry for my command

$ php run commands
List all available command names (useful for shell
autocomplete)
```
