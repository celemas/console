<?php

declare(strict_types=1);

namespace Celema\Console\Tests\Fixtures;

use Celema\Console\Args;
use Celema\Console\Command;
use Celema\Console\Io;

#[Command('bar:stuff', "Prints Bar's stuff to stdout")]
class BarStuff
{
	public function __invoke(Args $args, Io $output): int
	{
		$output->echo("Bar's stuff");

		return 0;
	}
}
