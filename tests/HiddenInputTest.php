<?php

declare(strict_types=1);

namespace Celema\Console\Tests;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Hidden prompts on a real (pseudo) terminal, which the in-memory
 * streams of the other tests cannot provide. Each test runs the
 * prompt in a child process; skipped where PHP has no PTY support.
 */
final class HiddenInputTest extends TestCase
{
	private const string TYPED = 'pty-typed-marker';

	/** @var resource|null */
	private mixed $process = null;

	/** @var resource|null */
	private mixed $terminal = null;

	private string $transcript = '';

	protected function tearDown(): void
	{
		if ($this->process !== null) {
			proc_terminate($this->process);
			proc_close($this->process);
		}

		parent::tearDown();
	}

	/** @return array<string, array{string}> */
	public static function inputs(): array
	{
		return [
			'terminal as STDIN' => ['stdin'],
			'terminal as input target, STDIN elsewhere' => ['tty'],
		];
	}

	#[DataProvider('inputs')]
	public function testSecretDisablesEchoWhileReading(string $input): void
	{
		$this->spawn($input);
		$this->expect('Password:');
		$this->awaitEcho(false);
		$this->type(self::TYPED . "\n");
		$this->expect('answer: ' . bin2hex(self::TYPED));

		$this->assertStringNotContainsString(self::TYPED, $this->transcript);
		$this->assertTrue($this->echoes(), 'The terminal echo was not restored');

		$this->type("\n");
		$this->assertSame(0, $this->finish());
	}

	public function testSecretRefusesToReadWhenEchoCannotBeDisabled(): void
	{
		// Without stty on the PATH the echo cannot be switched off.
		$this->spawn('stdin', ['PATH' => '/nonexistent']);
		$this->type(self::TYPED . "\n");
		$this->expect('refused: ');

		$this->assertStringNotContainsString('answer: ', $this->transcript);
		$this->assertSame(1, $this->finish());
	}

	/** @param array<string, string>|null $env */
	private function spawn(string $input, ?array $env = null): void
	{
		$descriptors = [
			$input === 'tty' ? ['file', '/dev/null', 'r'] : ['pty'],
			['pty'],
			['pty'],
		];

		// proc_open() warns where PTYs are unsupported.
		set_error_handler(static fn(): bool => true);

		try {
			$process = proc_open(
				[PHP_BINARY, __DIR__ . '/Fixtures/hidden-prompt.php', $input],
				$descriptors,
				$pipes,
				env_vars: $env,
			);
		} finally {
			restore_error_handler();
		}

		if ($process === false) {
			$this->markTestSkipped('PHP has no PTY support on this system');
		}

		$this->process = $process;
		$this->terminal = $pipes[1];
		stream_set_blocking($this->terminal, false);
	}

	private function type(string $text): void
	{
		fwrite($this->terminal, $text);
	}

	private function expect(string $text): void
	{
		$deadline = microtime(true) + 10;

		while (!str_contains($this->transcript, $text)) {
			if (microtime(true) > $deadline) {
				$this->fail("Timed out waiting for '{$text}' in: {$this->transcript}");
			}

			$read = [$this->terminal];
			$write = null;
			$except = null;

			if (stream_select($read, $write, $except, 0, 100_000) > 0) {
				$this->transcript .= (string) fread($this->terminal, 8192);
			}
		}
	}

	private function awaitEcho(bool $on): void
	{
		$deadline = microtime(true) + 10;

		while ($this->echoes() !== $on) {
			if (microtime(true) > $deadline) {
				$this->fail('Timed out waiting for the terminal echo to turn ' . ($on ? 'on' : 'off'));
			}

			usleep(20_000);
		}
	}

	/**
	 * Reads the echo flag through the PTY master, which shares the
	 * terminal settings of the child's side.
	 */
	private function echoes(): bool
	{
		$process = proc_open(['stty', '-a'], [0 => $this->terminal, 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
		$this->assertNotFalse($process);
		$settings = (string) stream_get_contents($pipes[1]);
		proc_close($process);

		return preg_match('/(?<![\w-])echo\b/', $settings) === 1;
	}

	private function finish(): int
	{
		$deadline = microtime(true) + 10;

		do {
			$status = proc_get_status($this->process);

			if (!$status['running']) {
				// Only the first status after the exit carries the code.
				proc_close($this->process);
				$this->process = null;

				return $status['exitcode'];
			}

			usleep(20_000);
		} while (microtime(true) < $deadline);

		$this->fail('Timed out waiting for the prompt process to exit');
	}
}
