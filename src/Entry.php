<?php

declare(strict_types=1);

namespace Celema\Console;

use Closure;
use ValueError;

/**
 * A registered command: its metadata plus the factory producing the
 * runnable instance on first use.
 *
 * @internal
 */
final class Entry
{
	private ?object $command = null;

	/** @param class-string $class */
	private function __construct(
		public readonly Command $meta,
		private readonly string $class,
		private readonly Closure $factory,
	) {}

	public static function fromInstance(object $command): self
	{
		return new self(Command::of($command), $command::class, static fn(): object => $command);
	}

	/** @param class-string $class */
	public static function fromClass(string $class): self
	{
		return new self(Command::of($class), $class, static fn(): object => new $class());
	}

	/** @param class-string $class */
	public static function fromFactory(string $class, Closure $factory): self
	{
		return new self(Command::of($class), $class, $factory);
	}

	public function command(): object
	{
		if ($this->command === null) {
			$command = ($this->factory)();

			// The metadata was read from the keyed class; an unrelated
			// object would be described by one class and run as another.
			if (!$command instanceof $this->class) {
				throw new ValueError(
					"Factory for command '{$this->meta->full()}' must return a {$this->class}",
				);
			}

			$this->command = $command;
		}

		return $this->command;
	}

	/** @return list<Opt> */
	public function opts(): array
	{
		return Opt::of($this->class);
	}

	/** @return list<Arg> */
	public function args(): array
	{
		return Arg::of($this->class);
	}
}
