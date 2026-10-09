<?php

declare(strict_types=1);

namespace Celema\Console\Tests\Fixtures;

use Celema\Console\Arg;
use Celema\Console\Command;
use Celema\Console\Io;
use Celema\Console\Opt;

#[Command('greet', 'Greets a name')]
class Greet
{
	public function __invoke(
		Io $output,
		#[Arg('Who to greet')]
		string $name = 'World',
		#[Opt('The greeting to use')]
		string $greeting = 'Hello',
	): int {
		$output->write("{$greeting}, {$name}");

		return 0;
	}
}
