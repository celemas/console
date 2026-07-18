<?php

declare(strict_types=1);

namespace Celema\Console\Tests;

use Celema\Console\Commands;
use Celema\Console\Runner;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * @internal
 */
class TestCase extends BaseTestCase
{
	public function getCommands(): Commands
	{
		return new Commands([
			new Fixtures\FooStuff(),
			new Fixtures\BarStuff(),
			new Fixtures\FooDrivel(),
			new Fixtures\Erring(),
		]);
	}

	public function getRunner(): Runner
	{
		// Route the error stream into the same buffer so output assertions
		// can capture messages that now go to STDERR.
		return new Runner($this->getCommands(), output: 'php://output', errorOutput: 'php://output');
	}
}
