<?php

declare(strict_types=1);

namespace Celemas\Cli\Tests\Fixtures;

use Celemas\Cli\Command;

class Plain extends Command
{
	protected string $name = 'plain';
	protected string $description = 'An ungrouped command';

	public function run(): int
	{
		$this->echo('Plain');

		return 0;
	}
}
