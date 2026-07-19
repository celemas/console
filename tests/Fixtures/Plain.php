<?php

declare(strict_types=1);

namespace Celema\Console\Tests\Fixtures;

use Celema\Console\Args;
use Celema\Console\Command;
use Celema\Console\Io;

#[Command('plain', 'An ungrouped command')]
class Plain
{
	public function __invoke(Args $args, Io $output): int
	{
		$output->echo('Plain');

		return 0;
	}
}
