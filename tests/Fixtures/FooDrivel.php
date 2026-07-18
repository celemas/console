<?php

declare(strict_types=1);

namespace Celema\Console\Tests\Fixtures;

use Celema\Console\Args;
use Celema\Console\Command;

class FooDrivel extends Command
{
	protected string $name = 'drivel';
	protected string $group = 'Foo';
	protected string $description = "Prints Foo's drivel to stdout";

	public function run(Args $args): int
	{
		$this->echo("Foo's drivel");

		return self::SUCCESS;
	}
}
