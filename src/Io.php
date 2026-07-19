<?php

declare(strict_types=1);

namespace Celema\Console;

use RuntimeException;
use ValueError;

/**
 * @api
 */
class Io
{
	private mixed $stream = null;
	private mixed $errorStream = null;
	private mixed $inputStream = null;
	private ?int $width = null;
	private array $fg = [
		'black' => [0, 30],
		'gray' => [1, 30],
		'grey' => [1, 30],
		'red' => [0, 31],
		'lightred' => [1, 31],
		'green' => [0, 32],
		'lightgreen' => [1, 32],
		'brown' => [0, 33],
		'yellow' => [1, 33],
		'blue' => [0, 34],
		'lightblue' => [1, 34],
		'purple' => [0, 35],
		'lightpurple' => [1, 35],
		'magenta' => [0, 35],
		'lightmagenta' => [1, 35],
		'cyan' => [0, 36],
		'lightcyan' => [1, 36],
		'lightgray' => [0, 37],
		'lightgrey' => [0, 37],
		'white' => [1, 37],
	];
	private array $bg = [
		'black' => 40,
		'red' => 41,
		'green' => 42,
		'yellow' => 43,
		'blue' => 44,
		'purple' => 45,
		'magenta' => 45,
		'cyan' => 46,
		'gray' => 47,
		'grey' => 47,
		'white' => 47,
	];

	public function __construct(
		protected readonly string $target = 'php://output',
		protected readonly string $errorTarget = 'php://stderr',
		protected readonly string $inputTarget = 'php://stdin',
	) {}

	public function echo(string $text, string $color = '', string $background = ''): void
	{
		$stream = $this->stdout();
		$this->write($stream, $this->styled($stream, $text, $color, $background));
	}

	public function echoln(string $text, string $color = '', string $background = ''): void
	{
		$stream = $this->stdout();
		$this->write($stream, $this->styled($stream, $text, $color, $background) . PHP_EOL);
	}

	public function echoErr(string $text, string $color = '', string $background = ''): void
	{
		$stream = $this->stderr();
		$this->write($stream, $this->styled($stream, $text, $color, $background));
	}

	public function echolnErr(string $text, string $color = '', string $background = ''): void
	{
		$stream = $this->stderr();
		$this->write($stream, $this->styled($stream, $text, $color, $background) . PHP_EOL);
	}

	public function info(string $message): void
	{
		$this->echoln($message);
	}

	public function success(string $message): void
	{
		$this->echoln($message, 'green');
	}

	public function warn(string $message): void
	{
		$this->echolnErr($message, 'yellow');
	}

	public function error(string $message): void
	{
		$this->echolnErr($message, 'red');
	}

	/**
	 * Prints the question and reads one line from the input stream.
	 *
	 * A trimmed empty answer (or end of input) yields the default. With
	 * `hidden` the terminal echo is switched off while typing, for example
	 * for passwords, and the answer keeps its whitespace; only the trailing
	 * newline is stripped. Without a terminal (or without `stty`, as on
	 * Windows) the input is simply read as is, visibly.
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

		if ($hidden && stream_isatty($stream)) {
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

	private function styled(mixed $stream, string $text, string $color, string $background): string
	{
		return $color !== '' || $background !== ''
			? $this->colorize($stream, $text, $color, $background)
			: $text;
	}

	private function write(mixed $stream, string $text): void
	{
		fwrite($stream, $text);
		fflush($stream);
	}

	/**
	 * Wraps the text in ANSI codes for the given color names.
	 *
	 * Unknown names throw a ValueError even when colors are disabled. The
	 * decision whether to emit codes is made against the regular output
	 * stream; the echo helpers decide against the stream they write to.
	 */
	public function color(string $text, string $color = '', string $background = ''): string
	{
		return $this->colorize($this->stdout(), $text, $color, $background);
	}

	private function colorize(mixed $stream, string $text, string $color, string $background): string
	{
		$colorCode = '';
		$backgroundCode = '';

		if ($color !== '') {
			$pair = $this->fg[$color] ?? throw new ValueError("Unknown color '{$color}'");
			$colorCode = "{$pair[0]};{$pair[1]}";
		}

		if ($background !== '') {
			$code = $this->bg[$background] ?? throw new ValueError(
				"Unknown background color '{$background}'",
			);
			$backgroundCode = (string) $code;
		}

		if (!$this->hasColorSupport($stream)) {
			return $text;
		}

		return $this->formatText($text, $colorCode, $backgroundCode);
	}

	public function indent(
		string $text,
		int $indent,
		?int $max = null,
	): string {
		$spaces = str_repeat(' ', $indent);
		$width = $this->terminalWidth() - $indent;

		if ($max !== null && $max < $width) {
			$width = $max;
		}

		$lines = explode("\n", wordwrap($text, $width, break: "\n"));

		return implode("\n", array_map(static fn($line) => $spaces . $line, $lines));
	}

	private function terminalWidth(): int
	{
		if ($this->width !== null) {
			return $this->width;
		}

		$columns = (int) getenv('COLUMNS');

		if ($columns < 1 && stream_isatty($this->stdout())) {
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

	private function formatText(string $text, string $colorCode, string $backgroundCode): string
	{
		if ($colorCode !== '' && $backgroundCode !== '') {
			return "\033[{$colorCode};{$backgroundCode}m{$text}\033[0m";
		}

		if ($colorCode !== '') {
			return "\033[{$colorCode}m{$text}\033[0m";
		}

		if ($backgroundCode !== '') {
			return "\033[{$backgroundCode}m{$text}\033[0m";
		}

		return $text;
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

		// Windows
		// @codeCoverageIgnoreStart
		if (DIRECTORY_SEPARATOR === '\\' && $terminal) {
			if (function_exists('sapi_windows_vt100_support')) {
				return sapi_windows_vt100_support($stream);
			}

			return (
				getenv('ANSICON') !== false
				|| getenv('ConEmuANSI') === 'ON'
				|| getenv('TERM') === 'xterm'
			);
		}

		// @codeCoverageIgnoreEnd

		return $terminal;
	}
}
