<?php

declare(strict_types=1);

namespace Celemas\Cli\Tests;

use Celemas\Cli\Opt;
use OutOfRangeException;

class OptTest extends TestCase
{
	public function testGetReturnsValueByIndex(): void
	{
		$opt = new Opt('first');
		$opt->set('second');

		$this->assertSame('first', $opt->get());
		$this->assertSame('second', $opt->get(1));
	}

	public function testGetThrowsForMissingIndex(): void
	{
		$opt = new Opt('only');

		$this->expectException(OutOfRangeException::class);
		$this->expectExceptionMessage('No value at index 5');
		$opt->get(5);
	}
}
