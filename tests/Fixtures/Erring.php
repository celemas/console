<?php

declare(strict_types=1);

namespace Celema\Console\Tests\Fixtures;

use Celema\Console\Args;
use Celema\Console\Command;
use Celema\Console\Io;
use Exception;

#[Command('err:err', 'Throws an error', group: 'Errors')]
class Erring
{
	public function __invoke(Args $args, Io $output): int
	{
		throw new Exception('Red herring');
	}
}
