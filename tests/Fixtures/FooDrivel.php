<?php

declare(strict_types=1);

namespace Celema\Console\Tests\Fixtures;

use Celema\Console\Args;
use Celema\Console\Command;
use Celema\Console\Io;

#[Command('foo:drivel', "Prints Foo's drivel to stdout")]
class FooDrivel
{
	public function __invoke(Args $args, Io $output): int
	{
		$output->echo("Foo's drivel");

		return 0;
	}
}
