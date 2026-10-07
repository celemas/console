<?php

declare(strict_types=1);

namespace Celema\Console;

use ReflectionParameter;
use ValueError;

/**
 * An `#[Arg]` parameter of a command's `__invoke()`.
 *
 * @internal
 */
final class Argument
{
	public readonly string $name;

	public function __construct(
		public readonly ReflectionParameter $parameter,
		public readonly Arg $arg,
		public readonly Type $type,
	) {
		$this->name = Signature::kebab($parameter->getName());
	}

	public function optional(): bool
	{
		return $this->parameter->isDefaultValueAvailable();
	}

	public function variadic(): bool
	{
		return $this->type->name === 'array';
	}

	public function convert(string $value): mixed
	{
		return (
			$this->type->convert($value)
				?? throw new ValueError("Argument '<{$this->name}>' expects {$this->type->expected()}, got '{$value}'")
		);
	}
}
