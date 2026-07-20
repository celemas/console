<?php

declare(strict_types=1);

namespace Celema\Console;

use Attribute;
use ReflectionClass;
use ValueError;

/**
 * Declares a class as a console command.
 *
 * The name may carry a group prefix separated by a single colon, e.g.
 * `db:migrate`; further colons are rejected.
 * The prefix namespaces the command on the command line and groups it in the
 * help overview under the capitalized prefix; `group` overrides that
 * displayed title.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Command
{
	public readonly string $name;
	public readonly string $prefix;

	public function __construct(
		string $name,
		public readonly string $description = '',
		public readonly string $group = '',
	) {
		$lower = strtolower($name);
		$prefix = '';
		$bare = $lower;

		if (str_contains($lower, ':')) {
			/** @var array{0: string, 1: string} $parts */
			$parts = explode(':', $lower, limit: 2);
			[$prefix, $bare] = $parts;

			// A single colon only: a second one would collide with the
			// prefixed lookup of another command.
			if ($prefix === '' || str_contains($bare, ':')) {
				throw new ValueError("Invalid command name '{$name}'");
			}
		}

		if ($bare === '') {
			throw new ValueError("Invalid command name '{$name}'");
		}

		$this->prefix = $prefix;
		$this->name = $bare;
	}

	public function full(): string
	{
		return $this->prefix === '' ? $this->name : "{$this->prefix}:{$this->name}";
	}

	public function title(): string
	{
		if ($this->group !== '') {
			return $this->group;
		}

		return $this->prefix === '' ? 'General' : ucfirst($this->prefix);
	}

	/**
	 * Reads the attribute off a command instance or class.
	 *
	 * @param class-string|object $command
	 */
	public static function of(object|string $command): self
	{
		$class = is_object($command) ? $command::class : $command;
		$attributes = new ReflectionClass($class)->getAttributes(self::class);

		if ($attributes === []) {
			throw new ValueError("Command class '{$class}' has no #[Command] attribute");
		}

		return $attributes[0]->newInstance();
	}
}
