---
title: Introduction
---
Celemas Cli is a command line interface helper like
[Laravel's Artisan](https://laravel.com/docs/9.x/artisan) with way less magic.

## Installation

```bash
composer require celemas/cli
```

## Quick Start

Create a Command:

```php
use Celemas\Cli\Command;

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
     * The entry point of the command.
     */
    public function run(): int
    {
        $this->echo("Run my command\n");
        
        // Output helpers with color support
        $this->info("Informational message");
        $this->success("Success message");
        $this->warn("Warning message");
        $this->error("Error message");
        
        // echoln adds a newline automatically
        $this->echoln("Message with automatic newline");

        return 0;
    }

    /**
     * Optional:
     * Used to add information to the commands help text
     * (e. g. `php run help <command>`)
     */
    public function help(): void
    {
        $this->helpHeader(withOptions: true);
        $this->helpOption('-s, --stuff <stuff>', 'Description of --stuff');
        $this->helpOption('-v, --verbose', 'Enable verbose output');
    }
}
```

## Features

### Output Methods

- `echo(string $message, string $color = '', string $background = '')`
  Output text
- `echoln(string $message, string $color = '', string $background = '')`
  Output text with newline
- `info(string $message)` - Output informational message
- `success(string $message)` - Output success message (green)
- `warn(string $message)` - Output warning message (yellow)
- `error(string $message)` - Output error message (red)
- `color(string $text, string $color, string $background = '')`
  Return colored text
- `indent(string $text, int $indent, ?int $max = null)`
  Indent and wrap text

### Available Colors

Foreground: `black`, `gray`/`grey`, `red`, `lightred`, `green`,
`lightgreen`, `brown`, `yellow`, `blue`, `lightblue`, `purple`,
`lightpurple`, `magenta`, `lightmagenta`, `cyan`, `lightcyan`,
`lightgray`/`lightgrey`, `white`

Background: `black`, `red`, `green`, `yellow`, `blue`, `purple`,
`magenta`, `cyan`, `gray`/`grey`, `white`

### Command-Line Options

Options support both space and equals syntax:

```bash
php run mycommand --key value
php run mycommand --key=value
php run mycommand --key="value with spaces"
```

Use the `Opts` class to parse command-line options in your commands.

### Built-in Commands

- `help` - Display help for all commands or a specific command
- `commands` - List all command names (useful for shell autocomplete)

### Debug Mode

Enable debug mode in the Runner to display full stack traces when
commands throw exceptions:

```php
$runner = new Runner($commands, debug: true);
```

Create a runner script, e. g. `run.php` or simply `run`:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Celemas\Cli\{Runner, Commands};
use MyCommand;

$commands = new Commands([new MyCommand()]);

// Optional: enable debug mode to show stack traces on errors
$runner = new Runner($commands, debug: false);
$runner->run();
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
