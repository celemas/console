<?php

declare(strict_types=1);

namespace Celema\Console;

use BackedEnum;
use ReflectionParameter;

/**
 * Renders a command's help screen from its declaration.
 *
 * Used by the Runner for `help <command>`; commands that intercept a
 * `--help` flag themselves can render the same screen via `showFor()`.
 *
 * @api
 */
final class Help
{
	/**
	 * The script name in the usage line defaults to `$_SERVER['argv'][0]`.
	 */
	public function __construct(
		private readonly Io $io,
		private readonly ?string $script = null,
	) {}

	/**
	 * Renders help for a command instance or class from its `#[Command]`
	 * attribute and `__invoke()` signature.
	 *
	 * @param class-string|object $command
	 */
	public function showFor(object|string $command): void
	{
		$class = is_object($command) ? $command::class : $command;
		$meta = Command::of($class);
		$signature = Signature::of($class, $meta->full());
		$arguments = $signature->arguments();
		$options = $signature->options();
		$script = $this->script ?? $_SERVER['argv'][0] ?? '';

		if ($meta->description !== '') {
			$this->io->echo("<yellow>Description:</yellow>\n  {$meta->description}\n\n");
		}

		$usage = "<yellow>Usage:</yellow>\n  php {$script} {$meta->full()}";

		foreach ($arguments as $argument) {
			$name = $this->argumentName($argument);
			$usage .= $argument->optional() ? " [{$name}]" : " {$name}";
		}

		$this->io->echo($usage . ($options === [] ? "\n" : " [options]\n"));
		$this->showArguments($arguments);
		$this->showOptions($options);
	}

	/** @param list<Argument> $arguments */
	private function showArguments(array $arguments): void
	{
		if ($arguments === []) {
			return;
		}

		$this->io->echo("\n<yellow>Arguments:</yellow>\n");

		foreach ($arguments as $argument) {
			$this->io->echo("    <green>{$this->argumentName($argument)}</green>\n");
			$this->showDescription($argument->arg->description, $argument->parameter, $argument->type);
		}
	}

	/** @param array<string, Option> $options */
	private function showOptions(array $options): void
	{
		if ($options === []) {
			return;
		}

		$this->io->echo("\n<yellow>Options:</yellow>\n");

		foreach ($options as $option) {
			$suffix = match (true) {
				$option->type->name === 'bool' => '',
				$option->opt->bare !== null => "[=<{$option->label()}>]",
				default => "=<{$option->label()}>",
			};

			$short = $option->opt->short;
			$flags = $short === '' ? $option->name . $suffix : "{$short}{$suffix}, {$option->name}{$suffix}";

			$this->io->echo('    <green>' . $this->io->escape($flags) . "</green>\n");
			$this->showDescription($option->opt->description, $option->parameter, $option->type);
		}
	}

	private function argumentName(Argument $argument): string
	{
		// Escaped: the <name> notation must not parse as markup.
		return $this->io->escape("<{$argument->name}>") . ($argument->variadic() ? '...' : '');
	}

	/**
	 * Renders the description followed by the choices and the default.
	 */
	private function showDescription(string $description, ReflectionParameter $parameter, Type $type): void
	{
		$parts = $description === '' ? [] : [$description];
		$choices = $type->choices();

		if ($choices !== []) {
			$parts[] = '[choices: ' . $this->io->escape(implode(', ', $choices)) . ']';
		}

		$default = $parameter->isDefaultValueAvailable() ? $this->text($parameter->getDefaultValue()) : '';

		if ($default !== '') {
			$parts[] = '[default: ' . $this->io->escape($default) . ']';
		}

		if ($parts !== []) {
			$this->io->echo($this->io->indent(implode(' ', $parts), 8, 80) . "\n");
		}
	}

	/**
	 * Renders a default value; null, booleans, and arrays render empty.
	 */
	private function text(mixed $value): string
	{
		if ($value instanceof BackedEnum) {
			$value = $value->value;
		}

		return is_string($value) || is_int($value) || is_float($value) ? (string) $value : '';
	}
}
