<?php

declare(strict_types=1);

namespace Celema\Console\Tests\Fixtures;

use Celema\Console\Args;
use Celema\Console\Command;

class Failing extends Command
{
	protected string $name = 'fail';
	protected string $group = 'Foo';
	protected string $description = 'Returns a failure code';

	public function run(Args $args): int
	{
		return self::FAILURE;
	}
}
