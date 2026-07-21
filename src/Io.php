<?php

declare(strict_types=1);

namespace Celema\Console;

use RuntimeException;

/**
 * The echo methods render the inline console markup, e.g.
 * `<green>done</green>` or `<strong>1</strong>`; see Markup for the tag
 * set and passthrough rules. Use `escape()` for text that must print
 * literally. The message helpers treat their input as plain text.
 *
 * @api
 */
class Io
{
	private readonly Markup $markup;
	private mixed $stream = null;
	private mixed $errorStream = null;
	private mixed $inputStream = null;
	private ?int $width = null;

	public function __construct(
		protected readonly string $target = 'php://stdout',
		protected readonly string $errorTarget = 'php://stderr',
		protected readonly string $inputTarget = 'php://stdin',
	) {
		$this->markup = new Markup();
	}

	public function echo(string $text): void
	{
		$stream = $this->stdout();
		$this->write($stream, $this->markup->render($text, $this->hasColorSupport($stream)));
	}

	public function echoln(string $text): void
	{
		$stream = $this->stdout();
		$this->write($stream, $this->markup->render($text, $this->hasColorSupport($stream)) . PHP_EOL);
	}

	public function echoErr(string $text): void
	{
		$stream = $this->stderr();
		$this->write($stream, $this->markup->render($text, $this->hasColorSupport($stream)));
	}

	public function echolnErr(string $text): void
	{
		$stream = $this->stderr();
		$this->write($stream, $this->markup->render($text, $this->hasColorSupport($stream)) . PHP_EOL);
	}

	/**
	 * Escapes markup tags and strips control characters (keeping
	 * newlines and tabs) so the text prints literally.
	 */
	public function escape(string $text): string
	{
		return $this->markup->escape($text);
	}

	public function info(string $message): void
	{
		$this->echoln($this->escape($message));
	}

	public function success(string $message): void
	{
		$this->echoln('<green>' . $this->escape($message) . '</green>');
	}

	public function warn(string $message): void
	{
		$this->echolnErr('<yellow>' . $this->escape($message) . '</yellow>');
	}

	public function error(string $message): void
	{
		$this->echolnErr('<red>' . $this->escape($message) . '</red>');
	}

	/**
	 * Prints the question and reads one line from the input stream.
	 *
	 * A trimmed empty answer (or end of input) yields the default. With
	 * `hidden` the terminal echo is switched off while typing, for example
	 * for passwords, and the answer keeps its whitespace; only the trailing
	 * newline is stripped. On Windows, or without a terminal, the input is
	 * simply read as is, visibly.
	 */
	public function ask(string $question, string $default = '', bool $hidden = false): string
	{
		$this->echo($question . ' ');
		$line = $this->readline($hidden);
		$answer = $hidden ? rtrim($line, characters: "\r\n") : trim($line);

		return $answer === '' ? $default : $answer;
	}

	/**
	 * Asks a yes/no question and returns the answer as bool.
	 *
	 * An empty answer yields the default; an answer starting with `y` or
	 * `Y` means yes, anything else no.
	 */
	public function confirm(string $question, bool $default = false): bool
	{
		$answer = strtolower($this->ask($question . ($default ? ' [Y/n]' : ' [y/N]')));

		if ($answer === '') {
			return $default;
		}

		return str_starts_with($answer, 'y');
	}

	private function readline(bool $hidden): string
	{
		$stream = $this->stdin();

		// No stty on Windows; shell_exec would leak its error output.
		if ($hidden && DIRECTORY_SEPARATOR !== '\\' && stream_isatty($stream)) {
			// @codeCoverageIgnoreStart
			/** @psalm-suppress ForbiddenCode */
			$previous = trim((string) shell_exec('stty -g'));

			/** @psalm-suppress ForbiddenCode */
			shell_exec('stty -echo');

			try {
				return (string) fgets($stream);
			} finally {
				// Restore the saved terminal state rather than assuming
				// echo was on, even when reading throws.
				/** @psalm-suppress ForbiddenCode */
				shell_exec($previous === '' ? 'stty echo' : 'stty ' . escapeshellarg($previous));
				$this->echo(PHP_EOL);
			}

			// @codeCoverageIgnoreEnd
		}

		return (string) fgets($stream);
	}

	private function write(mixed $stream, string $text): void
	{
		fwrite($stream, $text);
		fflush($stream);
	}

	/**
	 * Indents the text and wraps it on its visible width; markup tags
	 * and multibyte characters don't count. `$max` caps the total line
	 * width: the text wraps as if the terminal were at most that wide.
	 */
	public function indent(
		string $text,
		int $indent,
		?int $max = null,
	): string {
		$spaces = str_repeat(' ', $indent);
		$terminal = $this->terminalWidth();

		if ($max !== null && $max < $terminal) {
			$terminal = $max;
		}

		$width = $terminal - $indent;

		$lines = [];

		foreach (explode("\n", $text) as $line) {
			foreach ($this->wrap($line, $width) as $wrapped) {
				$lines[] = $wrapped === '' ? '' : $spaces . $wrapped;
			}
		}

		return implode("\n", $lines);
	}

	/**
	 * Wraps one line at spaces; a word longer than the width overflows.
	 *
	 * @return list<string>
	 */
	private function wrap(string $line, int $width): array
	{
		$lines = [];
		$current = null;
		$currentWidth = 0;

		foreach (explode(' ', $line) as $word) {
			$wordWidth = $this->markup->width($word);

			if ($current !== null && ($currentWidth + 1 + $wordWidth) <= $width) {
				$current .= ' ' . $word;
				$currentWidth += 1 + $wordWidth;

				continue;
			}

			if ($current !== null) {
				$lines[] = $current;
			}

			$current = $word;
			$currentWidth = $wordWidth;
		}

		$lines[] = (string) $current;

		return $lines;
	}

	private function terminalWidth(): int
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

	protected function stdout(): mixed
	{
		return $this->stream ??= $this->open($this->target, 'w');
	}

	protected function stderr(): mixed
	{
		return $this->errorStream ??= $this->open($this->errorTarget, 'w');
	}

	protected function stdin(): mixed
	{
		return $this->inputStream ??= $this->open($this->inputTarget, 'r');
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

	protected function hasColorSupport(mixed $stream): bool
	{
		$noColor = getenv('NO_COLOR');

		if ($noColor !== false && $noColor !== '') {
			return false;
		}

		$force = getenv('FORCE_COLOR');

		if ($force !== false) {
			return $force !== '0' && strtolower($force) !== 'false';
		}

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
}
