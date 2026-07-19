<?php

declare(strict_types=1);

namespace Celema\Console;

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
		$this->write($this->stdout(), $this->styled($text, $color, $background));
	}

	public function echoln(string $text, string $color = '', string $background = ''): void
	{
		$this->write($this->stdout(), $this->styled($text, $color, $background) . PHP_EOL);
	}

	public function echoErr(string $text, string $color = '', string $background = ''): void
	{
		$this->write($this->stderr(), $this->styled($text, $color, $background));
	}

	public function echolnErr(string $text, string $color = '', string $background = ''): void
	{
		$this->write($this->stderr(), $this->styled($text, $color, $background) . PHP_EOL);
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
	 * for passwords; without a terminal the input is simply read as is.
	 */
	public function ask(string $question, string $default = '', bool $hidden = false): string
	{
		$this->echo($question . ' ');
		$answer = trim($this->readline($hidden));

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
			shell_exec('stty -echo');
			$line = (string) fgets($stream);

			/** @psalm-suppress ForbiddenCode */
			shell_exec('stty echo');
			$this->echo(PHP_EOL);

			return $line;

			// @codeCoverageIgnoreEnd
		}

		return (string) fgets($stream);
	}

	private function styled(string $text, string $color, string $background): string
	{
		return $color || $background ? $this->color($text, $color, $background) : $text;
	}

	private function write(mixed $stream, string $text): void
	{
		fwrite($stream, $text);
		fflush($stream);
	}

	public function color(string $text, string $color = '', string $background = ''): string
	{
		if (!$this->hasColorSupport()) {
			return $text;
		}

		$colorCode = '';
		$backgroundCode = '';

		if ($color && array_key_exists($color, $this->fg)) {
			[$first, $second] = $this->fg[$color];
			$colorCode = "{$first};{$second}";
		}

		if ($background && array_key_exists($background, $this->bg)) {
			$backgroundCode = $this->bg[$background];
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

		if ($columns < 1) {
			/** @psalm-suppress ForbiddenCode */
			$columns = (int) shell_exec('tput cols');
		}

		if ($columns < 1) {
			// @codeCoverageIgnoreStart
			$columns = 80;

			// @codeCoverageIgnoreEnd
		}

		return $this->width = $columns;
	}

	private function formatText(string $text, string $colorCode, string|int $backgroundCode): string
	{
		if ($colorCode && $backgroundCode) {
			return "\033[{$colorCode};{$backgroundCode}m{$text}\033[0m";
		}

		if ($colorCode) {
			return "\033[{$colorCode}m{$text}\033[0m";
		}

		if ($backgroundCode) {
			return "\033[{$backgroundCode}m{$text}\033[0m";
		}

		return $text;
	}

	protected function stdout(): mixed
	{
		return $this->stream ??= fopen($this->target, mode: 'w');
	}

	protected function stderr(): mixed
	{
		return $this->errorStream ??= fopen($this->errorTarget, mode: 'w');
	}

	protected function stdin(): mixed
	{
		return $this->inputStream ??= fopen($this->inputTarget, mode: 'r');
	}

	protected function hasColorSupport(): bool
	{
		if (getenv('NO_COLOR') !== false) {
			return false;
		}

		// @codeCoverageIgnoreStart
		if (getenv('FORCE_COLOR') !== false || getenv('COLORTERM') !== false) {
			return true;
		}

		// Windows
		if (DIRECTORY_SEPARATOR === '\\') {
			if (function_exists('sapi_windows_vt100_support')) {
				return sapi_windows_vt100_support(STDOUT);
			}

			return (
				getenv('ANSICON') !== false
				|| getenv('ConEmuANSI') === 'ON'
				|| getenv('TERM') === 'xterm'
			);
		}

		if (function_exists('stream_isatty')) {
			return stream_isatty(STDOUT);
		}

		return false;

		// @codeCoverageIgnoreEnd
	}
}
