<?php

declare(strict_types=1);

namespace Celema\Console\Tests;

use Celema\Console\BufferedIo;
use Celema\Console\Commands;
use Celema\Console\Runner;
use Celema\Console\Tests\Fixtures\Plain;

class BufferedIoTest extends TestCase
{
	public function testCapturesOutputAndErrorsSeparately(): void
	{
		$out = new BufferedIo();
		$out->echo('to stdout');
		$out->echoErr('to stderr');

		$this->assertSame('to stdout', $out->output());
		$this->assertSame('to stderr', $out->errorOutput());
	}

	public function testReadingKeepsCapturing(): void
	{
		$out = new BufferedIo();
		$out->echo('first');

		$this->assertSame('first', $out->output());

		$out->echo(' second');

		$this->assertSame('first second', $out->output());
	}

	public function testColorsAreDisabled(): void
	{
		putenv('FORCE_COLOR=1');

		try {
			$out = new BufferedIo();
			$out->success('done');
			$out->error('failed');

			$this->assertSame('done' . PHP_EOL, $out->output());
			$this->assertSame('failed' . PHP_EOL, $out->errorOutput());
			$this->assertSame('plain', $out->color('plain', 'red'));
		} finally {
			putenv('FORCE_COLOR');
		}
	}

	public function testRunnerAcceptsAnOutputInstance(): void
	{
		$_SERVER['argv'] = ['run', 'plain'];
		$out = new BufferedIo();
		$runner = new Runner(new Commands([new Plain()]), $out);

		$this->assertSame(0, $runner->run());
		$this->assertSame('Plain', $out->output());
	}
}
