<?php

declare(strict_types=1);

namespace Celema\Console;

use Attribute;
use ReflectionClass;
use ValueError;

/**
 * Declares a class as a console command.
 *
 * The name consists of letters, digits, `.`, `_`, and `-`, starts with a
 * letter or digit, and is matched case-insensitively. It may carry a group
 * prefix of the same form, separated by a single colon, e.g. `db:migrate`;
 * any other name is rejected.
 * The prefix namespaces the command on the command line. The help overview
 * lists the command under its `group`, by default the capitalized prefix or,
 * without one, General; commands with the same group share a section.
 *
 * The attribute and its properties are public API; the methods serve the
 * runner.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Command
{
	/**
	 * A single colon only: a second one would collide with the prefixed
	 * lookup of another command.
	 */
	private const string NAME = '/^(?:([a-z0-9][a-z0-9._-]*):)?([a-z0-9][a-z0-9._-]*)\z/';

	public readonly string $name;
	public readonly string $prefix;

	public function __construct(
		string $name,
		public readonly string $description = '',
		public readonly string $group = '',
	) {
		if (preg_match(self::NAME, strtolower($name), $parts) !== 1) {
			throw new ValueError("Invalid command name '{$name}'");
		}

		$this->prefix = $parts[1];
		$this->name = $parts[2];
	}

	/** @internal */
	public function full(): string
	{
		return $this->prefix === '' ? $this->name : "{$this->prefix}:{$this->name}";
	}

	/** @internal */
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
	 * @internal
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
