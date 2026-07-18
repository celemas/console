<?php

declare(strict_types=1);

namespace Celema\Console\Tests\Fixtures;

use Celema\Console\Args;
use Celema\Console\Command;

class BarStuff extends Command
{
	protected string $name = 'stuff';
	protected string $group = 'Bar';
	protected string $description = "Prints Bar's stuff to stdout";

	public function run(Args $args): int
	{
		$this->echo("Bar's stuff");

		return self::SUCCESS;
	}
}
