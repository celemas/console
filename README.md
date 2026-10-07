# Celema Console

<!-- prettier-ignore-start -->
[![ci](https://codefloe.com/celema/console/badges/workflows/ci.yml/badge.svg?style=flat&logo=forgejo&logoColor=white&label=ci)](https://codefloe.com/celema/console/actions)
[![code coverage](https://img.shields.io/endpoint?url=https%3A%2F%2Fcov.celema.dev%2Fcelema%2Fconsole%2Fcode%2Fbadge.json)](https://cov.celema.dev/celema/console/code)
[![type coverage](https://img.shields.io/endpoint?url=https%3A%2F%2Fcov.celema.dev%2Fcelema%2Fconsole%2Ftypes%2Fbadge-cover.json)](https://cov.celema.dev/celema/console/types)
[![psalm level](https://img.shields.io/endpoint?url=https%3A%2F%2Fcov.celema.dev%2Fcelema%2Fconsole%2Ftypes%2Fbadge-level.json)](https://cov.celema.dev/celema/console/types)
[![Software License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE.md)
<!-- prettier-ignore-end -->

A command line interface helper.

## Features

- Commands are plain classes marked with a `#[Command]` attribute — no base class, free constructors
- Arguments and options are `__invoke()` parameters marked `#[Arg]` or `#[Opt]`, converted to their declared types — `string`, `int`, `float`, `bool`, `array`, or a backed enum — with defaults from the signature
- Option groups: classes bundling `#[Opt]` constructor parameters, shared by several commands
- Automatic help generation from the `#[Command]` attribute and the `__invoke()` signature
- Strict by default: the parameters are a command's complete interface — an unknown or malformed option (with a "Did you mean" suggestion), a value of the wrong type, a missing required argument, or an undeclared positional aborts with exit code 2 before the command runs; an `array` argument takes open-ended input
- Raw access to the parsed options and positionals via an injected `Args` object
- Lazy command construction: factories or an optional class resolver run only for the invoked command
- Anonymous classes as lightweight one-off commands — attributes work inline
- Built-in color support with per-stream terminal detection and `NO_COLOR`/`FORCE_COLOR` handling
- Command help with `php run help <command>`
- Built-in `commands` command for shell autocomplete
- `--key=value` options (repeatable) and boolean `--flag` / `-h` flags; `--` ends option parsing
- Io helpers for output: `info()`, `success()`, `warn()`, `error()`, `echoln()` (warnings and errors go to STDERR)
- Inline markup for styled output: `<strong>`, `<em>`, `<dim>`, `<u>`, the ANSI colors — `<green>`, `<bright-red>`, `<bg-blue>`, ... — and truecolor hex tags: `<#ff7313>`, `<bg-#ff7313>`
- Interactive prompts: `ask()` (optionally with hidden input), `confirm()`, and `choice()`
- `BufferedIo` for testing commands without output buffering or escape-code stripping
- Text formatting helpers: `indent()` wraps, `pad()` aligns, `rule()` separates — all on the visible width, markup and multibyte aware
- `Table` for minimal scc-style column output — no borders, no cell wrapping
- Debug mode for detailed error traces

## Installation

```bash
composer require celema/console
```

## Quick Start

A command is a plain invokable class with a `#[Command]` attribute:

```php
use Celema\Console\{Arg, Command, Opt, Io};

#[Command('grp:mycommand', 'This is my command')]
class MyCommand
{
    public function __invoke(
        Io $io,
        #[Arg('Who to greet')]
        string $name = 'world',
        #[Opt('Rows per batch', short: '-b')]
        int $batch = 500,
        #[Opt('Skip the safety net')]
        bool $force = false,
    ): int {
        $io->info("Running my command for {$name} in batches of {$batch}");
        $io->success('Command completed!');

        return 0;
    }
}
```

`__invoke()` must declare the return type `int` (the exit code). An `Io` parameter is injected; `#[Arg]` parameters take the positional arguments in order and `#[Opt]` parameters the options, named after the parameter in kebab-case. Options use `--key=value` (a flag like `--force` has no value), and every option needs a default.

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
$ php run mycommand alice -b=100
Running my command for alice in batches of 100
Command completed!
```

## Resolving Commands

Register class names with an optional resolver to construct commands through your container or application runtime:

```php
$commands = new Commands(
    [MyCommand::class],
    resolve: $container->get(...),
);
$runner = new Runner($commands);
```

The resolver receives the registered class name and must return an instance of that class or a subclass. Commands are resolved only when invoked, then cached per registration; instances and explicit factories bypass the resolver. Without a resolver, class names use a zero-argument constructor. Console has no container dependency.

Commands can also receive `Io` through their constructors. Configure the resolver to supply the same `Io` instance you pass to `new Runner($commands, $io)`; Console does not register services in your container. See [Registering Commands](docs/index.md#registering-commands) for details.

## Mutation testing

Mutation testing with [Infection](https://infection.github.io/) is not part of `composer ci`, but the CI workflow runs it after the coverage step and enforces the minimum mutation score from `infection.json5.dist`. Pushes only mutate the changed lines; a weekly scheduled run covers the whole codebase. Run it locally with:

```console
composer mutation
```

Reports are written to `.infection/`.

## License

This project is licensed under the [MIT license](LICENSE.md).
