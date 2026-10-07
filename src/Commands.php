<?php

declare(strict_types=1);

namespace Celema\Console;

use Closure;
use ValueError;

/**
 * Collects command registrations.
 *
 * Accepts command instances, class-strings, and lazy factories keyed by
 * class-string. A resolver constructs class-string registrations; without
 * one, they need a zero-argument constructor:
 *
 *     $commands = new Commands([
 *         new Greet($translator),
 *         Simple::class,
 *         Expensive::class => fn() => new Expensive($db),
 *     ]);
 *
 * Commands carry their metadata in a #[Command] attribute. For a
 * lightweight one-off, register an anonymous class — attributes work
 * inline:
 *
 *     $commands->add(new #[Command('cache:clear', 'Clears the cache')] class {
 *         public function __invoke(Io $io): int { ... }
 *     });
 *
 * @api
 */
final class Commands
{
	/** @var list<Entry> */
	private array $entries = [];

	/** @var null|Closure(class-string): object */
	private readonly ?Closure $resolve;

	/**
	 * The resolver is called only for an invoked class-string registration.
	 * Instances and explicit factories bypass it. Resolved commands are
	 * cached per registration, also when added to another collection.
	 *
	 * @param null|callable(class-string): object $resolve
	 */
	public function __construct(
		array|object|string $commands = [],
		?callable $resolve = null,
	) {
		$this->resolve = $resolve === null ? null : Closure::fromCallable($resolve);
		$this->add($commands);
	}

	public function add(array|object|string $commands): void
	{
		if ($commands instanceof Closure) {
			throw new ValueError(
				'Closure commands are not supported; use an anonymous class with a #[Command] attribute',
			);
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
			$class = $this->validClass($commands);
			$resolve = $this->resolve;
			$this->entries[] = $resolve === null
				? Entry::fromClass($class)
				: Entry::fromFactory($class, static fn() => $resolve($class));

			return;
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
