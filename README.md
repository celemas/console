# Celema Console

<!-- prettier-ignore-start -->
[![ci](https://codeberg.org/celema/console/badges/workflows/ci.yml/badge.svg?style=flat&logo=codeberg&logoColor=white&label=ci)](https://codeberg.org/celema/console/actions)
[![code coverage](https://img.shields.io/endpoint?url=https%3A%2F%2Fcov.celema.dev%2Fcelema%2Fconsole%2Fcode%2Fbadge.json)](https://cov.celema.dev/celema/console/code)
[![type coverage](https://img.shields.io/endpoint?url=https%3A%2F%2Fcov.celema.dev%2Fcelema%2Fconsole%2Ftypes%2Fbadge-cover.json)](https://cov.celema.dev/celema/console/types)
[![psalm level](https://img.shields.io/endpoint?url=https%3A%2F%2Fcov.celema.dev%2Fcelema%2Fconsole%2Ftypes%2Fbadge-level.json)](https://cov.celema.dev/celema/console/types)
[![Software License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE.md)
<!-- prettier-ignore-end -->

A command line interface helper.

## Features

- Commands are plain classes marked with a `#[Command]` attribute — no base class, free constructors
- Automatic help generation from `#[Command]`, `#[Arg]`, and `#[Opt]` attributes
- Declared options are validated: unknown or malformed options abort with a "Did you mean" suggestion
- Parsed options and positional arguments via an injected `Args` object
- Lazy command construction: factories run only for the invoked command
- Closures as lightweight one-off commands
- Built-in color support with per-stream terminal detection and `NO_COLOR`/`FORCE_COLOR` handling
- Command help with `php run help <command>`
- Built-in `commands` command for shell autocomplete
- `--key=value` options (repeatable) and boolean `--flag` / `-h` flags
- Io helpers for output: `info()`, `success()`, `warn()`, `error()`, `echoln()` (warnings and errors go to STDERR)
- Interactive prompts: `ask()` (optionally with hidden input) and `confirm()`
- `BufferedIo` for testing commands without output buffering or escape-code stripping
- Text indentation and wrapping with `indent()`
- Debug mode for detailed error traces
- 100% test coverage

## Installation

```bash
composer require celema/console
```

## Quick Start

A command is a plain invokable class with a `#[Command]` attribute:

```php
use Celema\Console\{Args, Command, Opt, Io};

#[Command('grp:mycommand', 'This is my command')]
#[Opt('--force', 'Skip the safety net')]
class MyCommand
{
    public function __invoke(Args $args, Io $io): int
    {
        $name = $args->positional(0, 'world');
        $io->info("Running my command for {$name}");
        $io->success('Command completed!');

        return 0;
    }
}
```

`__invoke()` must declare the return type `int` (the exit code). Its `Args` and `Io` parameters are matched by type, not position: each is optional and their order is free, but no other parameters are allowed.

Options use `--key=value` (a bare `--flag` is a boolean); every other argument is a positional. Read them from the injected `Args`:

```php
$name = $args->positional(0);        // first positional, or null
$conn = $args->opt('--conn', 'sqlite'); // option value, or the default
$force = $args->has('--force');      // boolean flag
```

Create a runner script and pass its exit code to `exit()`:

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use Celema\Console\{Runner, Commands};

$commands = new Commands([new MyCommand()]);
$runner = new Runner($commands);

exit($runner->run());
```

Run your command:

```bash
$ php run mycommand alice
Running my command for alice
Command completed!
```

## License

This project is licensed under the [MIT license](LICENSE.md).
