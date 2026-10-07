<?php

declare(strict_types=1);

namespace Celema\Console\Tests\Fixtures;

use Celema\Console\Arg;
use Celema\Console\Command;
use Celema\Console\Io;

#[Command('greet:injected', 'Greets a name with injected output')]
final class InjectedGreet
{
	public function __construct(
		private readonly Io $io,
	) {}

	public function __invoke(#[Arg('Who to greet')] string $name): int
	{
		$this->io->echo("Hello, {$name}");

		return 0;
	}
}
