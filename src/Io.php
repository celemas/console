<?php

declare(strict_types=1);

namespace Celema\Console;

use Stringable;
use ValueError;

/**
 * The output methods take a template and its arguments: the template is
 * markup, e.g. `<green>done</green>` or `<strong>%d</strong>`, the
 * arguments are data. They fill the template's sprintf() conversions
 * and print as plain text, so no escaping is needed:
 *
 *     $io->error('Cannot read <strong>%s</strong>', $path);
 *
 * Without arguments the template is not formatted, so `%` needs no
 * doubling. Markup never fails: a tag without its partner prints
 * literally. See Markup for the tag set.
 *
 * The terminal decides where the text goes and what it supports: the
 * process streams by default, a Buffer in tests.
 *
 * @api
 */
final class Io
{
	private readonly Markup $markup;

	public function __construct(
		private readonly Terminal $terminal = new Stdio(),
	) {
		$this->markup = new Markup();
	}

	public function line(string $template = '', float|int|string|Stringable ...$args): void
	{
		$this->output($template, $args, PHP_EOL);
	}

	/**
	 * Like line(), without the line break.
	 */
	public function write(string $template, float|int|string|Stringable ...$args): void
	{
		$this->output($template, $args, '');
	}

	public function success(string $template, float|int|string|Stringable ...$args): void
	{
		$this->output($template, $args, PHP_EOL, style: 'green');
	}

	/**
	 * Writes a yellow line to the error output.
	 */
	public function warn(string $template, float|int|string|Stringable ...$args): void
	{
		$this->output($template, $args, PHP_EOL, error: true, style: 'yellow');
	}

	/**
	 * Writes a red line to the error output.
	 */
	public function error(string $template, float|int|string|Stringable ...$args): void
	{
		$this->output($template, $args, PHP_EOL, error: true, style: 'red');
	}

	/**
	 * Escapes markup tags and strips control characters (keeping
	 * newlines and tabs) so the text prints literally where it is
	 * concatenated into a template; arguments need no escaping.
	 *
	 * The result is meant for the output methods: text ending in a
	 * backslash gets an invisible marker that keeps a following tag
	 * from reading as escaped.
	 */
	public function escape(string $text): string
	{
		return $this->markup->escape($text);
	}

	/**
	 * Pads the text with spaces to the visible width `$width`; markup
	 * tags and multibyte characters don't count, wider text is
	 * returned unchanged.
	 */
	public function pad(string $text, int $width, Align $align = Align::Left): string
	{
		return $this->markup->pad($text, $width, $align);
	}

	/**
	 * Writes a horizontal rule: the char repeated across the terminal.
	 *
	 * `$max` caps the width like in `indent()`. The char may be a
	 * multi-char pattern and may carry markup — the repeat count uses
	 * its visible width: `$io->rule('<dim>─</dim>')` draws a dim line.
	 */
	public function rule(string $char = '─', ?int $max = null): void
	{
		$width = $this->terminal->width();

		if ($max !== null && $max < $width) {
			$width = $max;
		}

		$unit = $this->markup->width($char);

		if ($unit < 1) {
			throw new ValueError("Rule char '{$char}' has no visible width");
		}

		$this->line(str_repeat($char, intdiv($width, $unit)));
	}

	/**
	 * Prints the question, and the default if there is one, and reads one
	 * line. A trimmed empty answer, or the end of input, yields the
	 * default.
	 */
	public function ask(string $question, string $default = ''): string
	{
		$this->write($question);

		if ($default !== '') {
			$this->write(' [%s]', $default);
		}

		$this->write(' ');
		$answer = trim($this->terminal->read() ?? '');

		return $answer === '' ? $default : $answer;
	}

	/**
	 * Asks for input that must not show while typing, such as a password.
	 *
	 * The answer keeps its whitespace. Where the terminal cannot hide the
	 * input, it throws rather than reading visibly; see Stdio::read().
	 */
	public function secret(string $question): string
	{
		$this->write($question . ' ');

		return $this->terminal->read(hidden: true) ?? '';
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

	/**
	 * Asks to pick one of the options and returns its key.
	 *
	 * The labels are listed numbered from 1, and the prompt shows the
	 * default's number: `[1]`. An empty answer, or the end of input,
	 * yields the default, the first option unless given; any other answer
	 * must be a listed number — an invalid one asks again.
	 *
	 * @template K of array-key
	 *
	 * @param array<K, string> $options
	 * @param K|null $default
	 *
	 * @return K
	 */
	public function choice(string $question, array $options, int|string|null $default = null): int|string
	{
		if ($options === []) {
			throw new ValueError('Choice needs options');
		}

		$default ??= array_key_first($options);

		if (!array_key_exists($default, $options)) {
			throw new ValueError("Choice default '{$default}' is not an option");
		}

		$keys = array_keys($options);
		$number = array_flip($keys)[$default] + 1;
		$this->line($question);

		foreach (array_values($options) as $i => $label) {
			$this->line('  %d) %s', $i + 1, $label);
		}

		while (true) {
			$answer = $this->ask("[{$number}]");

			if ($answer === '') {
				return $keys[$number - 1];
			}

			if (preg_match('/^\d+\z/', $answer) === 1 && (int) $answer >= 1 && (int) $answer <= count($keys)) {
				return $keys[(int) $answer - 1];
			}
		}
	}

	/**
	 * Whether someone can see the prompts and answer them.
	 */
	public function interactive(): bool
	{
		return $this->terminal->interactive();
	}

	/**
	 * The terminal width in columns.
	 */
	public function width(): int
	{
		return $this->terminal->width();
	}

	/** @param array<float|int|string|Stringable> $args */
	private function output(string $template, array $args, string $end, bool $error = false, string $style = ''): void
	{
		$text = $this->markup->render($template, array_values($args), $this->terminal->colors($error), $style);
		$this->terminal->write($text . $end, $error);
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
		$terminal = $this->terminal->width();

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
}
