<?php

declare(strict_types=1);

namespace Celema\Console;

use Attribute;
use ReflectionAttribute;
use ReflectionClass;

/**
 * Describes one positional argument of a command in its help output.
 *
 * Arguments render in declaration order in the usage line — `<name>`, or
 * `[<name>]` when optional — and as entries of an "Arguments:" section.
 *
 * A `variadic` argument must be the last one and accepts the remaining
 * positionals: at least one, or any number when also `optional`. It
 * renders as `<name>...`.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class Arg
{
	public function __construct(
		public readonly string $name,
		public readonly string $description,
		public readonly bool $optional = false,
		public readonly bool $variadic = false,
	) {}

	/**
	 * Reads all argument attributes off a command instance or class.
	 *
	 * @param class-string|object $command
	 * @return list<self>
	 */
	public static function of(object|string $command): array
	{
		$class = is_object($command) ? $command::class : $command;

		return array_map(
			static fn(ReflectionAttribute $attribute): self => $attribute->newInstance(),
			new ReflectionClass($class)->getAttributes(self::class),
		);
	}
}
