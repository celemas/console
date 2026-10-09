<?php

declare(strict_types=1);

namespace Celema\Console\Tests;

use Celema\Console\Stdio;
use RuntimeException;

class StdioTest extends TestCase
{
	/** @var list<string> */
	private array $files = [];

	protected function tearDown(): void
	{
		// Clear every mutated variable here so a failing assertion cannot
		// leak state into later tests.
		putenv('COLUMNS');
		putenv('NO_COLOR');
		putenv('FORCE_COLOR');

		foreach ($this->files as $file) {
			unlink($file);
		}

		parent::tearDown();
	}

	private function file(string $contents = ''): string
	{
		$file = (string) tempnam(sys_get_temp_dir(), prefix: 'cli');
		file_put_contents($file, $contents);
		$this->files[] = $file;

		return $file;
	}

	public function testWritesToTheTargets(): void
	{
		$out = $this->file();
		$err = $this->file();
		$stdio = new Stdio($out, $err);
		$stdio->write('one ');
		$stdio->write('two');
		$stdio->write('error ', error: true);
		$stdio->write('again', error: true);

		$this->assertSame('one two', file_get_contents($out));
		$this->assertSame('error again', file_get_contents($err));
	}

	public function testReadsLinesFromTheInputTarget(): void
	{
		$stdio = new Stdio(input: $this->file("Charly\r\n\nlast"));

		$this->assertSame('Charly', $stdio->read());
		$this->assertSame('', $stdio->read());
		$this->assertSame('last', $stdio->read());
		$this->assertNull($stdio->read());
	}

	public function testHiddenReadIsVisibleWithoutTerminal(): void
	{
		$out = $this->file();
		$stdio = new Stdio($out, input: $this->file("  secret  \n"));

		$this->assertSame('  secret  ', $stdio->read(hidden: true));
		$this->assertSame('', file_get_contents($out));
	}

	public function testNoColorsWithoutTerminalOrEnvOverride(): void
	{
		putenv('NO_COLOR');
		putenv('FORCE_COLOR');
		$stdio = new Stdio($this->file(), $this->file());

		$this->assertFalse($stdio->colors());
		$this->assertFalse($stdio->colors(error: true));
	}

	public function testColorTermDoesNotColorRedirectedStreams(): void
	{
		putenv('NO_COLOR');
		putenv('FORCE_COLOR');
		$colorterm = getenv('COLORTERM');
		putenv('COLORTERM=truecolor');

		try {
			$this->assertFalse(new Stdio($this->file())->colors());
		} finally {
			putenv($colorterm === false ? 'COLORTERM' : "COLORTERM={$colorterm}");
		}
	}

	public function testForceColor(): void
	{
		$stdio = new Stdio($this->file(), $this->file());

		putenv('FORCE_COLOR=1');
		$this->assertTrue($stdio->colors());
		$this->assertTrue($stdio->colors(error: true));

		foreach (['0', 'false', 'FALSE'] as $value) {
			putenv("FORCE_COLOR={$value}");
			$this->assertFalse($stdio->colors(), "FORCE_COLOR={$value}");
		}
	}

	public function testNoColorWinsOverForceColor(): void
	{
		putenv('FORCE_COLOR=1');
		$stdio = new Stdio($this->file());

		$this->assertTrue($stdio->colors());

		putenv('NO_COLOR=1');
		$this->assertFalse($stdio->colors());
	}

	public function testEmptyNoColorIsIgnored(): void
	{
		putenv('NO_COLOR=');
		putenv('FORCE_COLOR=1');

		$this->assertTrue(new Stdio($this->file())->colors());
	}

	public function testExplicitColorsOverrideTheEnvironment(): void
	{
		putenv('NO_COLOR=1');
		$this->assertTrue(new Stdio('/nonexistent/out', colors: true)->colors());

		putenv('NO_COLOR');
		putenv('FORCE_COLOR=1');
		$this->assertFalse(new Stdio('/nonexistent/out', colors: false)->colors(error: true));
	}

	public function testNotInteractiveWithoutTerminal(): void
	{
		$this->assertFalse(new Stdio($this->file(), input: $this->file())->interactive());
	}

	public function testWidthComesFromColumnsAndIsReadOnce(): void
	{
		putenv('COLUMNS=40');
		$stdio = new Stdio($this->file());

		$this->assertSame(40, $stdio->width());

		putenv('COLUMNS=80');
		$this->assertSame(40, $stdio->width());

		putenv('COLUMNS=1');
		$this->assertSame(1, new Stdio($this->file())->width());
	}

	public function testUnopenableTargetThrows(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage("Could not open stream '/nonexistent/dir/out'");

		new Stdio('/nonexistent/dir/out')->write('test');
	}

	public function testUnopenableTargetDoesNotEmitWarning(): void
	{
		error_clear_last();

		try {
			new Stdio(input: '/nonexistent/dir/in')->read();
			$this->fail('RuntimeException was not thrown');
		} catch (RuntimeException) {
			$this->assertNull(error_get_last());
		}
	}
}
