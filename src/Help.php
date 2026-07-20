<?php

declare(strict_types=1);

namespace Celema\Console;

/**
 * Renders a command's help screen from its attributes.
 *
 * Used by the Runner for `help <command>`; commands that intercept a
 * `--help` flag themselves can render the same screen via `showFor()`.
 *
 * @api
 */
final class Help
{
	public function __construct(
		private readonly Io $io,
	) {}

	/**
	 * @param list<Opt> $opts
	 * @param list<Arg> $arguments
	 */
	public function show(Command $meta, array $opts = [], array $arguments = []): void
	{
		$script = $_SERVER['argv'][0] ?? '';

		if ($meta->description !== '') {
			$this->io->echo("<yellow>Description:</yellow>\n  {$meta->description}\n\n");
		}

		$usage = "<yellow>Usage:</yellow>\n  php {$script} {$meta->full()}";

		foreach ($arguments as $argument) {
			// Escaped: the <name> notation must not parse as markup.
			$name = $this->io->escape("<{$argument->name}>") . ($argument->variadic ? '...' : '');
			$usage .= $argument->optional ? " [{$name}]" : " {$name}";
		}

		$this->io->echo($usage . ($opts === [] ? "\n" : " [options]\n"));
		$this->showArguments($arguments);
		$this->showOptions($opts);
	}

	/** @param list<Arg> $arguments */
	private function showArguments(array $arguments): void
	{
		if ($arguments === []) {
			return;
		}

		$this->io->echo("\n<yellow>Arguments:</yellow>\n");

		foreach ($arguments as $argument) {
			$name = $this->io->escape("<{$argument->name}>") . ($argument->variadic ? '...' : '');
			$this->io->echo("    <green>{$name}</green>\n");

			if ($argument->description !== '') {
				$this->io->echo($this->io->indent($argument->description, 8, 80) . "\n");
			}
		}
	}

	/** @param list<Opt> $opts */
	private function showOptions(array $opts): void
	{
		if ($opts === []) {
			return;
		}

		$this->io->echo("\n<yellow>Options:</yellow>\n");

		foreach ($opts as $opt) {
			$suffix = match (true) {
				$opt->value === '' => '',
				$opt->optionalValue => "[=<{$opt->value}>]",
				default => "=<{$opt->value}>",
			};

			$option = $opt->short === ''
				? $opt->long . $suffix
				: "{$opt->short}{$suffix}, {$opt->long}{$suffix}";

			$description = $opt->default === ''
				? $opt->description
				: "{$opt->description} [default: {$opt->default}]";

			$this->io->echo('    <green>' . $this->io->escape($option) . "</green>\n");

			if ($description !== '') {
				$this->io->echo($this->io->indent($description, 8, 80) . "\n");
			}
		}
	}

	/**
	 * Renders help for a command instance or class from its attributes.
	 *
	 * @param class-string|object $command
	 */
	public function showFor(object|string $command): void
	{
		$this->show(Command::of($command), Opt::of($command), Arg::of($command));
	}
}
