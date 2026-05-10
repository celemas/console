# Celemas Cli

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE.md)  
[![Codacy Badge](https://app.codacy.com/project/badge/Grade/4b33c04781b04209b9d1f8d50f5f3c1a)](https://app.codacy.com/gh/celemas/cli/dashboard?utm_source=gh&utm_medium=referral&utm_content=&utm_campaign=Badge_grade)  
[![Codacy Badge](https://app.codacy.com/project/badge/Coverage/4b33c04781b04209b9d1f8d50f5f3c1a)](https://app.codacy.com/gh/celemas/cli/dashboard?utm_source=gh&utm_medium=referral&utm_content=&utm_campaign=Badge_coverage)  
[![Psalm coverage](https://shepherd.dev/github/celemas/cli/coverage.svg?)](https://shepherd.dev/github/celemas/cli)  
[![Psalm level](https://shepherd.dev/github/celemas/cli/level.svg?)](https://celemas.dev/cli)

A command line interface helper.

## Features

- Simple command creation with automatic help generation
- Built-in color support for terminal output
- Command-specific help with `php run help <command>`
- Built-in `commands` command for shell autocomplete
- Support for `--key=value` and `--key value` option syntax
- Output helpers: `info()`, `success()`, `warn()`, `error()`, `echoln()`
- Text indentation and wrapping with `indent()`
- Debug mode for detailed error traces
- 100% test coverage

## Installation

```bash
composer require celemas/cli
```

## Quick Start

Create a command by extending `Celemas\Cli\Command`:

```php
use Celemas\Cli\Command;

class MyCommand extends Command {
    protected string $name = 'mycommand';
    protected string $group = 'MyGroup';
    protected string $description = 'This is my command';

    public function run(): int
    {
        $this->info("Running my command");
        $this->success("Command completed!");
        return 0;
    }
}
```

Create a runner script:

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use Celemas\Cli\{Runner, Commands};

$commands = new Commands([new MyCommand()]);
$runner = new Runner($commands);
$runner->run();
```

Run your command:

```bash
$ php run mycommand
Running my command
Command completed!
```

## License

This project is licensed under the [MIT license](LICENSE.md).
