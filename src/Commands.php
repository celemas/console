<?php

declare(strict_types=1);

namespace Celema\Console;

use Closure;
use ValueError;

/**
 * Collects command registrations.
 *
 * Accepts command instances, class-strings of zero-argument constructible
 * commands, lazy factories keyed by class-string, and named closures:
 *
 *     $commands = new Commands([
 *         new Greet($translator),
 *         Simple::class,
 *         Expensive::class => fn() => new Expensive($db),
 *     ]);
 *     $commands->add('cache:clear', 'Clears the cache', fn(Args $args, Io $io): int => ...);
 *
 * Class-based commands carry their metadata in a #[Command] attribute;
 * named closures get theirs from the add() arguments.
 *
 * @api
 */
final class Commands
{
	/** @var list<Entry> */
	private array $entries = [];

	public function __construct(array|object|string $commands = [])
	{
		$this->add($commands);
	}

	public function add(
		array|object|string $commands,
		string $description = '',
		?Closure $command = null,
	): void {
		if ($command !== null) {
			if (!is_string($commands)) {
				throw new ValueError('A closure command requires a name');
			}

			$this->entries[] = Entry::fromClosure($commands, $description, $command);

			return;
		}

		if ($commands instanceof Commands) {
			foreach ($commands->entries() as $entry) {
				$this->entries[] = $entry;
			}

			return;
		}

		if (is_array($commands)) {
			$this->addArray($commands);

			return;
		}

		if (is_string($commands)) {
			$this->entries[] = Entry::fromClass($this->validClass($commands));

			return;
		}

		if ($commands instanceof Closure) {
			throw new ValueError('A closure command requires a name and description');
		}

		$this->entries[] = Entry::fromInstance($commands);
	}

	/** @return list<Entry> */
	public function entries(): array
	{
		return $this->entries;
	}

	private function addArray(array $commands): void
	{
		foreach ($commands as $key => $item) {
			if (is_string($key)) {
				if (!$item instanceof Closure) {
					throw new ValueError("Factory for command class '{$key}' must be a closure");
				}

				$this->entries[] = Entry::fromFactory($this->validClass($key), $item);

				continue;
			}

			if (!is_object($item) && !is_string($item)) {
				throw new ValueError('Invalid command registration');
			}

			$this->add($item);
		}
	}

	/** @return class-string */
	private function validClass(string $class): string
	{
		if (!class_exists($class)) {
			throw new ValueError("Unknown command class '{$class}'");
		}

		return $class;
	}
}
