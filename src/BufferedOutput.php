<?php

declare(strict_types=1);

namespace Celema\Console;

use Override;

/**
 * An Output that captures everything in memory instead of writing to the
 * terminal.
 *
 * Made for command tests: pass it to the command (or the Runner) and assert
 * on `output()` and `errorOutput()`. Colors are always disabled, so
 * assertions need no escape-code stripping. The `$input` string feeds
 * `ask()` and `confirm()`, one line per prompt.
 *
 * @api
 */
final class BufferedOutput extends Output
{
	private mixed $inputBuffer = null;

	public function __construct(string $input = '')
	{
		parent::__construct('php://memory', 'php://memory');

		if ($input !== '') {
			$stream = $this->stdin();
			fwrite($stream, $input);
			rewind($stream);
		}
	}

	#[Override]
	protected function stdin(): mixed
	{
		// The parent opens its input target read-only, which php://memory
		// honors, so the buffer needs its own read-write stream.
		return $this->inputBuffer ??= fopen('php://memory', mode: 'w+');
	}

	public function output(): string
	{
		return $this->contents($this->stdout());
	}

	public function errorOutput(): string
	{
		return $this->contents($this->stderr());
	}

	private function contents(mixed $stream): string
	{
		$position = (int) ftell($stream);
		rewind($stream);
		$contents = (string) stream_get_contents($stream);
		fseek($stream, $position);

		return $contents;
	}

	#[Override]
	protected function hasColorSupport(): bool
	{
		return false;
	}
}
