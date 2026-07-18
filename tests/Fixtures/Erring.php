<?php

declare(strict_types=1);

namespace Celema\Console\Tests\Fixtures;

use Celema\Console\Args;
use Celema\Console\Command;
use Exception;

class Erring extends Command
{
	protected string $name = 'err';
	protected string $group = 'Errors';
	protected string $prefix = 'err';
	protected string $description = 'Throws an error';

	public function run(Args $args): int
	{
		throw new Exception('Red herring');
	}
}
