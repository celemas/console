<?php

declare(strict_types=1);

namespace Celema\Console\Tests\Fixtures;

use Celema\Console\Args;
use Celema\Console\Command;
use Celema\Console\Io;

#[Command('greet', 'Greets a name')]
class Greet
{
	public function __invoke(Args $args, Io $output): int
	{
		$name = $args->positional(0, 'World');
		$greeting = $args->opt('--greeting', 'Hello');
		$output->echo("{$greeting}, {$name}");

		return 0;
	}
}
