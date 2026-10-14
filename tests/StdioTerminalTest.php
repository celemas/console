<?php

declare(strict_types=1);

namespace Celema\Console\Tests;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Stdio on a real (pseudo) terminal, which the other tests cannot
 * provide: colors and width depend on the terminal type there. Each
 * test runs the output in a child process; skipped where PHP has no PTY
 * support.
 */
final class StdioTerminalTest extends TestCase
{
	/** @return array<string, array{string, string}> */
	public static function terminalTypes(): array
	{
		return [
			'colors' => ['xterm', "\033[32mcolored\033[0m "],
			'no colors' => ['dumb', 'colored '],
		];
	}

	#[DataProvider('terminalTypes')]
	public function testColorsFollowTheTerminalType(string $term, string $output): void
	{
		$this->assertStringStartsWith($output, $this->transcript(['TERM' => $term]));
	}

	public function testWidthFallsBackQuietlyWithoutTerminalType(): void
	{
		$this->assertSame("\033[32mcolored\033[0m 80\r\n", $this->transcript([]));
	}

	/**
	 * Runs the child with only these variables and PATH, so the test
	 * environment's COLUMNS, NO_COLOR, and FORCE_COLOR stay out.
	 *
	 * @param array<string, string> $env
	 */
	private function transcript(array $env): string
	{
		// proc_open() warns where PTYs are unsupported, and reading the
		// terminal after the child exited may fail with EIO.
		set_error_handler(static fn(): bool => true);

		try {
			$process = proc_open(
				[PHP_BINARY, __DIR__ . '/Fixtures/terminal-output.php'],
				[['file', '/dev/null', 'r'], ['pty'], ['pty']],
				$pipes,
				env_vars: ['PATH' => (string) getenv('PATH'), ...$env],
			);

			if ($process === false) {
				$this->markTestSkipped('PHP has no PTY support on this system');
			}

			$output = (string) stream_get_contents($pipes[1]);
			proc_close($process);
		} finally {
			restore_error_handler();
		}

		return $output;
	}
}
