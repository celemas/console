<?php

declare(strict_types=1);

namespace Celema\Console;

use Override;
use RuntimeException;

/**
 * The process streams, or other targets such as files or `/dev/tty`.
 *
 * Targets open on first use; one that cannot be opened throws a
 * RuntimeException then. Colors are detected per stream unless
 * `$colors` decides for both.
 *
 * @api
 */
final class Stdio implements Terminal
{
	private mixed $stream = null;
	private mixed $errorStream = null;
	private mixed $inputStream = null;
	private ?int $width = null;

	public function __construct(
		private readonly string $output = 'php://stdout',
		private readonly string $errorOutput = 'php://stderr',
		private readonly string $input = 'php://stdin',
		private readonly ?bool $colors = null,
	) {}

	#[Override]
	public function write(string $text, bool $error = false): void
	{
		$stream = $this->stream($error);
		fwrite($stream, $text);
		fflush($stream);
	}

	/**
	 * Hidden input switches the terminal echo off with stty and throws a
	 * RuntimeException if that fails. On Windows, or without a terminal,
	 * the line is read visibly.
	 */
	#[Override]
	public function read(bool $hidden = false): ?string
	{
		$stream = $this->stdin();

		// No stty on Windows: hidden input reads visibly there.
		if ($hidden && DIRECTORY_SEPARATOR !== '\\' && stream_isatty($stream)) {
			// @codeCoverageIgnoreStart
			// Needs a real terminal; HiddenInputTest drives this in a child
			// process, outside the coverage run.
			$previous = $this->stty($stream, '-g');
			$this->stty($stream, '-echo');

			try {
				return $this->line(fgets($stream));
			} finally {
				// Restore the saved terminal state rather than assuming
				// echo was on, even when reading throws.
				$this->stty($stream, $previous === '' ? 'echo' : $previous);
				$this->write(PHP_EOL);
			}

			// @codeCoverageIgnoreEnd
		}

		return $this->line(fgets($stream));
	}

	/**
	 * `NO_COLOR` turns colors off, `FORCE_COLOR` on (`0` or `false` off);
	 * otherwise only a terminal gets them.
	 */
	#[Override]
	public function colors(bool $error = false): bool
	{
		if ($this->colors !== null) {
			return $this->colors;
		}

		$noColor = getenv('NO_COLOR');

		if ($noColor !== false && $noColor !== '') {
			return false;
		}

		$force = getenv('FORCE_COLOR');

		if ($force !== false) {
			return $force !== '0' && strtolower($force) !== 'false';
		}

		$stream = $this->stream($error);
		$terminal = stream_isatty($stream);

		// @codeCoverageIgnoreStart
		if (DIRECTORY_SEPARATOR === '\\' && $terminal) {
			// VT100 processing is off by default in cmd/PowerShell;
			// enabling it reports whether the console supports it.
			return sapi_windows_vt100_support($stream, enable: true);
		}

		// @codeCoverageIgnoreEnd

		return $terminal;
	}

	/**
	 * True when both the input and the output are terminals.
	 */
	#[Override]
	public function interactive(): bool
	{
		return stream_isatty($this->stdin()) && stream_isatty($this->stdout());
	}

	/**
	 * `COLUMNS`, else the terminal's width, else 80; read once.
	 */
	#[Override]
	public function width(): int
	{
		if ($this->width !== null) {
			return $this->width;
		}

		$columns = (int) getenv('COLUMNS');

		// No tput on Windows; shell_exec would leak its error output.
		if ($columns < 1 && DIRECTORY_SEPARATOR !== '\\' && stream_isatty($this->stdout())) {
			// @codeCoverageIgnoreStart
			/** @psalm-suppress ForbiddenCode */
			$columns = (int) shell_exec('tput cols');

			// @codeCoverageIgnoreEnd
		}

		if ($columns < 1) {
			// @codeCoverageIgnoreStart
			$columns = 80;

			// @codeCoverageIgnoreEnd
		}

		return $this->width = $columns;
	}

	private function line(string|false $line): ?string
	{
		return $line === false ? null : rtrim($line, characters: "\r\n");
	}

	/**
	 * Runs stty on the input terminal and returns its output.
	 *
	 * The stream becomes stty's STDIN: the process STDIN may be another
	 * file, e.g. when only the prompts read from `/dev/tty`. Failures
	 * throw, so a hidden prompt never reads while echo is still on.
	 *
	 * @codeCoverageIgnore
	 */
	private function stty(mixed $stream, string $arg): string
	{
		set_error_handler(static fn(): bool => true);

		try {
			$process = proc_open(['stty', $arg], [0 => $stream, 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
		} finally {
			restore_error_handler();
		}

		if ($process === false) {
			throw new RuntimeException('Could not run stty to hide the input');
		}

		$output = (string) stream_get_contents($pipes[1]);
		$error = trim((string) stream_get_contents($pipes[2]));

		if (proc_close($process) !== 0) {
			throw new RuntimeException('Could not switch the terminal echo for hidden input: ' . $error);
		}

		return trim($output);
	}

	private function stream(bool $error): mixed
	{
		return $error ? $this->stderr() : $this->stdout();
	}

	private function stdout(): mixed
	{
		return $this->stream ??= $this->open($this->output, 'w');
	}

	private function stderr(): mixed
	{
		return $this->errorStream ??= $this->open($this->errorOutput, 'w');
	}

	private function stdin(): mixed
	{
		return $this->inputStream ??= $this->open($this->input, 'r');
	}

	private function open(string $target, string $mode): mixed
	{
		set_error_handler(static fn(): bool => true);

		try {
			$stream = fopen($target, $mode);
		} finally {
			restore_error_handler();
		}

		if ($stream === false) {
			throw new RuntimeException("Could not open stream '{$target}'");
		}

		return $stream;
	}
}
