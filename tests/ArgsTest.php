<?php

declare(strict_types=1);

namespace Celema\Console\Tests;

use Celema\Console\Args;

class ArgsTest extends TestCase
{
	public function testParsesOptionValues(): void
	{
		$args = new Args(['--conn=sqlite', '--host=localhost:3306']);

		$this->assertTrue($args->has('--conn'));
		$this->assertSame('sqlite', $args->opt('--conn'));
		$this->assertSame('localhost:3306', $args->opt('--host'));
	}

	public function testSplitsOnFirstEqualsOnly(): void
	{
		$args = new Args(['--data=key=value']);

		$this->assertSame('key=value', $args->opt('--data'));
	}

	public function testRepeatedOptionsAccumulate(): void
	{
		$args = new Args(['--tag=a', '--tag=b']);

		$this->assertSame(['a', 'b'], $args->opts('--tag'));
		$this->assertSame('a', $args->opt('--tag'));
	}

	public function testBooleanFlags(): void
	{
		$args = new Args(['--force', '-h', '--force']);

		$this->assertTrue($args->has('--force'));
		$this->assertTrue($args->has('-h'));
		// A flag carries no value, so lookups return the defaults.
		$this->assertSame('', $args->opt('--force'));
		$this->assertSame('fallback', $args->opt('--force', 'fallback'));
		$this->assertSame([], $args->opts('--force'));
	}

	public function testPositionals(): void
	{
		$args = new Args(['up', '--conn=sqlite', 'down']);

		$this->assertSame(['up', 'down'], $args->positionals());
		$this->assertSame('up', $args->positional(0));
		$this->assertSame('down', $args->positional(1));
		$this->assertSame('sqlite', $args->opt('--conn'));
	}

	public function testDefaultsForMissingKeys(): void
	{
		$args = new Args([]);

		$this->assertFalse($args->has('--conn'));
		$this->assertSame('pgsql', $args->opt('--conn', 'pgsql'));
		$this->assertSame(['x'], $args->opts('--conn', ['x']));
		$this->assertNull($args->positional(0));
		$this->assertSame('def', $args->positional(3, 'def'));
		$this->assertSame([], $args->positionals());
	}
}
