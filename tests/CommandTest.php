<?php

declare(strict_types=1);

namespace Celema\Console\Tests;

use Celema\Console\Output;
use Celema\Console\Tests\Fixtures\Erring;
use Celema\Console\Tests\Fixtures\FooStuff;
use RuntimeException;

class CommandTest extends TestCase
{
	public function testCommandGetters(): void
	{
		$_SERVER['argv'] = ['run'];
		$foo = new FooStuff();

		$this->assertSame('stuff', $foo->name());
		$this->assertSame("Prints Foo's stuff to stdout", $foo->description());
		$this->assertSame('run', $foo->script());

		// Implicit prefix
		$this->assertSame('Foo', $foo->group());
		$this->assertSame('foo', $foo->prefix());

		// Explicit prefix
		$err = new Erring();
		$this->assertSame('Errors', $err->group());
		$this->assertSame('err', $err->prefix());
	}

	public function testEchoFails(): void
	{
		$foo = new FooStuff();

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Output missing');
		$foo->echo('error');
	}

	public function testEchoLineFails(): void
	{
		$foo = new FooStuff();

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Output missing');
		$foo->echoln('error');
	}

	public function testColorFails(): void
	{
		$foo = new FooStuff();

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Output missing');
		$foo->color('error', '#ffffff');
	}

	public function testIndentFails(): void
	{
		$foo = new FooStuff();

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Output missing');
		$foo->indent('error', 1);
	}

	public function testInfoMethod(): void
	{
		$foo = new FooStuff();
		$output = new Output('php://output');
		$foo->output($output);

		ob_start();
		$foo->info('Information message');
		$result = ob_get_clean();

		$this->assertSame("Information message\n", $result);
	}

	public function testSuccessMethod(): void
	{
		$foo = new FooStuff();
		$output = new Output('php://output');
		$foo->output($output);

		ob_start();
		$foo->success('Success message');
		$result = ob_get_clean();

		$this->assertStringContainsString('Success message', $result);
	}

	public function testWarnMethod(): void
	{
		$foo = new FooStuff();
		// warn() writes to STDERR; capture it by pointing the error stream here.
		$output = new Output('php://output', 'php://output');
		$foo->output($output);

		ob_start();
		$foo->warn('Warning message');
		$result = ob_get_clean();

		$this->assertStringContainsString('Warning message', $result);
	}

	public function testErrorMethod(): void
	{
		$foo = new FooStuff();
		$output = new Output('php://output', 'php://output');
		$foo->output($output);

		ob_start();
		$foo->error('Error message');
		$result = ob_get_clean();

		$this->assertStringContainsString('Error message', $result);
	}
}
