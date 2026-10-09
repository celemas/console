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
	 * Prints the question and reads one line from the input stream.
	 *
	 * A trimmed empty answer (or end of input) yields the default. With
	 * `hidden` the terminal echo is switched off while typing, for example
	 * for passwords, and the answer keeps its whitespace; only the trailing
	 * newline is stripped. If the echo cannot be switched off on a terminal,
	 * a RuntimeException is thrown before reading. On Windows, or without
	 * a terminal, the input is simply read as is, visibly.
	 */
	public function ask(string $question, string $default = '', bool $hidden = false): string
	{
		$this->write($question . ' ');
		$line = $this->terminal->read($hidden) ?? '';
		$answer = $hidden ? $line : trim($line);

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

	/**
	 * Asks to pick from a numbered list and returns the chosen option.
	 *
	 * The options render one per line, numbered from 1, then the
	 * prompt shows the default number: `[1]`. An empty answer (or end
	 * of input) yields the default's option; anything else must be a
	 * listed number — an invalid answer asks again.
	 *
	 * @param list<string> $options
	 */
	public function choice(string $question, array $options, int $default = 1): string
	{
		if ($options === []) {
			throw new ValueError('Choice needs options');
		}

		if ($default < 1 || $default > count($options)) {
			throw new ValueError("Choice default {$default} is out of range");
		}

		$this->line($question);

		foreach ($options as $i => $option) {
			$this->line('  ' . ($i + 1) . ') ' . $option);
		}

		while (true) {
			$answer = $this->ask("[{$default}]");

			if ($answer === '') {
				return $options[$default - 1];
			}

			if (preg_match('/^\d+\z/', $answer) === 1 && (int) $answer >= 1 && (int) $answer <= count($options)) {
				return $options[(int) $answer - 1];
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
