<?php

declare(strict_types=1);

namespace Celema\Console;

use BackedEnum;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * The declared type of an `#[Arg]` or `#[Opt]` parameter.
 *
 * @internal
 */
final class Type
{
	private function __construct(
		public readonly string $name,
	) {}

	/**
	 * The parameter's type, or null if it is not a supported one.
	 */
	public static function of(ReflectionParameter $parameter): ?self
	{
		$type = $parameter->getType();

		if (!$type instanceof ReflectionNamedType) {
			return null;
		}

		$name = $type->getName();

		return match ($name) {
			'string', 'int', 'float', 'bool', 'array' => new self($name),
			default => is_subclass_of($name, BackedEnum::class) ? new self($name) : null,
		};
	}

	/**
	 * Converts a command-line value, or returns null if it does not fit.
	 *
	 * Not for `bool`, which takes no value, nor `array`, which collects
	 * the strings as they are.
	 */
	public function convert(string $value): string|int|float|BackedEnum|null
	{
		return match ($this->name) {
			'int' => filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
			'float' => filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE),
			'string' => $value,
			default => $this->case($value),
		};
	}

	/**
	 * Describes the values that convert, for error messages.
	 */
	public function expected(): string
	{
		return match ($this->name) {
			'int' => 'an integer',
			'float' => 'a number',
			default => 'one of ' . implode(', ', $this->choices()),
		};
	}

	/**
	 * The backing values of an enum type, otherwise none.
	 *
	 * @return list<string>
	 */
	public function choices(): array
	{
		$enum = $this->name;

		if (!is_subclass_of($enum, BackedEnum::class)) {
			return [];
		}

		return array_map(static fn(BackedEnum $case): string => (string) $case->value, $enum::cases());
	}

	private function case(string $value): ?BackedEnum
	{
		/** @var class-string<BackedEnum> $enum */
		$enum = $this->name;

		// Compared as strings: under strict types, tryFrom() rejects the
		// string input of an int-backed enum.
		foreach ($enum::cases() as $case) {
			if ((string) $case->value === $value) {
				return $case;
			}
		}

		return null;
	}
}
