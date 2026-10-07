<?php

declare(strict_types=1);

namespace Celema\Console;

use Celema\Console\Exception\InvalidUsage;
use ReflectionParameter;

/**
 * An `#[Opt]` parameter of a command's `__invoke()` or of an option group's
 * constructor.
 *
 * @internal
 */
final class Option
{
	public readonly string $name;

	/**
	 * @param ?string $group The `__invoke()` parameter of the option group
	 *     declaring the option, if any
	 */
	public function __construct(
		public readonly ReflectionParameter $parameter,
		public readonly Opt $opt,
		public readonly Type $type,
		public readonly ?string $group = null,
	) {
		$this->name = '--' . Signature::kebab($parameter->getName());
	}

	/**
	 * The `<value>` label of a value-taking option.
	 */
	public function label(): string
	{
		return $this->opt->value === '' ? substr($this->name, offset: 2) : $this->opt->value;
	}

	/**
	 * Reads the value of the option, which occurs in the input.
	 *
	 * @param int $occurrences How often the option occurs, bare or with a
	 *     value; Args merges the occurrences
	 */
	public function value(Args $args, int $occurrences): mixed
	{
		$values = $args->opts($this->name);

		if ($this->type->name === 'bool') {
			if ($values !== []) {
				throw new InvalidUsage("Option '{$this->name}' does not accept a value");
			}

			return true;
		}

		// Every occurrence needs a value, also when a repetition provides
		// one: `--host --host=x` must not hide the bare `--host`.
		if ($args->bare($this->name)) {
			if ($this->opt->bare === null) {
				throw new InvalidUsage("Option '{$this->name}' requires a value: {$this->name}=<{$this->label()}>");
			}

			$values[] = $this->opt->bare;
		}

		if ($this->type->name === 'array') {
			return $values;
		}

		if ($occurrences !== 1) {
			throw new InvalidUsage("Option '{$this->name}' accepts only one value");
		}

		return (
			$this->type->convert($values[0])
				?? throw new InvalidUsage(
					"Option '{$this->name}' expects {$this->type->expected()}, got '{$values[0]}'",
				)
		);
	}
}
