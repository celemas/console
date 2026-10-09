<?php

declare(strict_types=1);

namespace Celema\Console;

use Override;

/**
 * A terminal in memory, made for tests.
 *
 * It captures the output and the error output separately and answers
 * the prompts from `$input`, one line each. Colors are off, so
 * assertions need no escape-code stripping; the other settings let a
 * test pretend to be an interactive terminal of a given width.
 *
 *     $buffer = new Buffer("yes\n");
 *     $command(io: new Io($buffer));
 *     $this->assertSame('Done' . PHP_EOL, $buffer->output());
 *
 * @api
 */
final class Buffer implements Terminal
{
	private string $output = '';
	private string $errorOutput = '';

	public function __construct(
		private string $input = '',
		private readonly bool $interactive = false,
		private readonly int $width = 80,
		private readonly bool $colors = false,
	) {}

	#[Override]
	public function write(string $text, bool $error = false): void
	{
		if ($error) {
			$this->errorOutput .= $text;
		} else {
			$this->output .= $text;
		}
	}

	#[Override]
	public function read(bool $hidden = false): ?string
	{
		if ($this->input === '') {
			return null;
		}

		$lines = explode("\n", $this->input, limit: 2);
		$this->input = $lines[1] ?? '';

		return rtrim($lines[0], characters: "\r");
	}

	#[Override]
	public function colors(bool $error = false): bool
	{
		return $this->colors;
	}

	#[Override]
	public function interactive(): bool
	{
		return $this->interactive;
	}

	#[Override]
	public function width(): int
	{
		return $this->width;
	}

	public function output(): string
	{
		return $this->output;
	}

	public function errorOutput(): string
	{
		return $this->errorOutput;
	}
}
