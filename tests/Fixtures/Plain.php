<?php

declare(strict_types=1);

namespace Celema\Console\Tests\Fixtures;

use Celema\Console\Args;
use Celema\Console\Command;

class Plain extends Command
{
	protected string $name = 'plain';
	protected string $description = 'An ungrouped command';

	public function run(Args $args): int
	{
		$this->echo('Plain');

		return self::SUCCESS;
	}
}
