<?php

declare(strict_types=1);

namespace Celema\Console\Tests;

use Celema\Console\Buffer;

class BufferTest extends TestCase
{
	public function testCapturesOutputAndErrorsSeparately(): void
	{
		$buffer = new Buffer();
		$buffer->write('to stdout');
		$buffer->write('to stderr', error: true);
		$buffer->write(' and more');

		$this->assertSame('to stdout and more', $buffer->output());
		$this->assertSame('to stderr', $buffer->errorOutput());
	}

	public function testReadsTheInputLineByLine(): void
	{
		$buffer = new Buffer("Charly\r\n\nsecond\nlast");

		$this->assertSame('Charly', $buffer->read());
		$this->assertSame('', $buffer->read(hidden: true));
		$this->assertSame('second', $buffer->read());
		$this->assertSame('last', $buffer->read());
		$this->assertNull($buffer->read());
	}

	public function testEmptyInputEndsAtOnce(): void
	{
		$this->assertNull(new Buffer()->read());
		$this->assertSame('', new Buffer("\n")->read());
	}

	public function testDefaultsToAPlainNonInteractiveTerminal(): void
	{
		$buffer = new Buffer();

		$this->assertFalse($buffer->colors());
		$this->assertFalse($buffer->colors(error: true));
		$this->assertFalse($buffer->interactive());
		$this->assertSame(80, $buffer->width());
	}

	public function testPretendsToBeTheGivenTerminal(): void
	{
		$buffer = new Buffer(interactive: true, width: 120, colors: true);

		$this->assertTrue($buffer->colors());
		$this->assertTrue($buffer->colors(error: true));
		$this->assertTrue($buffer->interactive());
		$this->assertSame(120, $buffer->width());
	}
}
