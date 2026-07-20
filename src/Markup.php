<?php

declare(strict_types=1);

namespace Celema\Console;

use ValueError;

/**
 * Renders the inline console markup to ANSI escape codes.
 *
 * Style tags are `<strong>`, `<em>`, `<dim>`, and `<u>`; color tags are
 * the ANSI names — `<red>`, `<bright-red>`, `<gray>` — and the same set
 * with a `bg-` prefix for backgrounds. Tags compose by nesting, the
 * innermost tag wins on conflict.
 *
 * Only exact known tags are parsed; everything else — `<info@example.com>`,
 * generics, unknown names — passes through untouched. A backslash renders
 * a known tag literally: `\<green>`. Structural mistakes (a mismatched,
 * dangling, or unclosed tag) throw a ValueError, also when colors are
 * disabled, so they surface in tests.
 *
 * @internal
 */
final class Markup
{
	private const string RESET = "\033[0m";

	/** SGR codes by tag name. */
	private const array TAGS = [
		'strong' => '1',
		'em' => '3',
		'dim' => '2',
		'u' => '4',
		'black' => '30',
		'red' => '31',
		'green' => '32',
		'yellow' => '33',
		'blue' => '34',
		'magenta' => '35',
		'cyan' => '36',
		'white' => '37',
		'gray' => '90',
		'bright-black' => '90',
		'bright-red' => '91',
		'bright-green' => '92',
		'bright-yellow' => '93',
		'bright-blue' => '94',
		'bright-magenta' => '95',
		'bright-cyan' => '96',
		'bright-white' => '97',
		'bg-black' => '40',
		'bg-red' => '41',
		'bg-green' => '42',
		'bg-yellow' => '43',
		'bg-blue' => '44',
		'bg-magenta' => '45',
		'bg-cyan' => '46',
		'bg-white' => '47',
		'bg-gray' => '100',
		'bg-bright-black' => '100',
		'bg-bright-red' => '101',
		'bg-bright-green' => '102',
		'bg-bright-yellow' => '103',
		'bg-bright-blue' => '104',
		'bg-bright-magenta' => '105',
		'bg-bright-cyan' => '106',
		'bg-bright-white' => '107',
	];

	/** @var non-empty-string */
	private readonly string $split;

	/** @var non-empty-string */
	private readonly string $tag;

	public function __construct()
	{
		$names = implode('|', array_keys(self::TAGS));
		$this->split = "#(\\\\?</?(?:{$names})>)#";
		$this->tag = "#^\\\\?</?(?:{$names})>$#";
	}

	/**
	 * Renders the markup as escape codes, or strips it without `$colors`.
	 */
	public function render(string $text, bool $colors): string
	{
		if (!str_contains($text, '<')) {
			return $text;
		}

		/** @var list<string> $parts */
		$parts = (array) preg_split(
			$this->split,
			$text,
			flags: PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY,
		);
		$out = '';
		$stack = [];

		foreach ($parts as $part) {
			if (preg_match($this->tag, $part) !== 1) {
				$out .= $part;

				continue;
			}

			if ($part[0] === '\\') {
				$out .= substr($part, offset: 1);

				continue;
			}

			if ($part[1] === '/') {
				$name = substr($part, offset: 2, length: -1);
				$last = array_pop($stack);

				if ($last === null) {
					throw new ValueError("Markup tag '</{$name}>' has no opening tag");
				}

				if ($last !== $name) {
					throw new ValueError("Markup tag '<{$last}>' closed by '</{$name}>'");
				}

				if ($colors) {
					// A blanket reset, then re-apply the enclosing tags.
					$out .= self::RESET;

					foreach ($stack as $open) {
						$out .= "\033[" . self::TAGS[$open] . 'm';
					}
				}

				continue;
			}

			$name = substr($part, offset: 1, length: -1);
			$stack[] = $name;

			if ($colors) {
				$out .= "\033[" . self::TAGS[$name] . 'm';
			}
		}

		if ($stack !== []) {
			throw new ValueError("Unclosed markup tag '<{$stack[array_key_last($stack)]}>'");
		}

		return $out;
	}

	/**
	 * Escapes known tags and strips control characters so the text
	 * prints literally.
	 *
	 * Everything C0 except newline and tab is removed, DEL included, so
	 * arbitrary text cannot inject terminal escape sequences (ESC, BEL,
	 * carriage returns, ...).
	 */
	public function escape(string $text): string
	{
		$text = (string) preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/', replacement: '', subject: $text);

		return (string) preg_replace($this->split, replacement: '\\\\$0', subject: $text);
	}

	/**
	 * The visible width of the text: tags collapse to nothing, an
	 * escaped tag to the tag without its backslash.
	 */
	public function width(string $text): int
	{
		if (str_contains($text, '<')) {
			$text = (string) preg_replace_callback(
				$this->split,
				static fn(array $match): string => $match[0][0] === '\\' ? substr($match[0], offset: 1) : '',
				$text,
			);
		}

		return mb_strwidth($text);
	}
}
