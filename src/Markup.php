<?php

declare(strict_types=1);

namespace Celema\Console;

use ValueError;

/**
 * Renders the inline console markup to ANSI escape codes.
 *
 * Style tags are `<strong>`, `<em>`, `<dim>`, and `<u>`; color tags are
 * the ANSI names — `<red>`, `<bright-red>`, `<gray>` — and the same set
 * with a `bg-` prefix for backgrounds. Truecolor hex tags take a
 * lowercase six-digit code: `<#ff7313>`, `<bg-#ff7313>`. Tags compose
 * by nesting, the innermost tag wins on conflict.
 *
 * Only exact known tags are parsed; everything else — `<info@example.com>`,
 * generics, unknown names — passes through untouched. A backslash renders
 * a known tag literally: `\<green>`. A tag without its partner — dangling,
 * mismatched, or unclosed — prints literally too: rendering never fails.
 *
 * Arguments fill the template's sprintf() conversions and print as plain
 * text, never as markup. Control characters other than newlines and tabs
 * are dropped from both, so no text can inject terminal escape sequences;
 * only templates keep carriage returns, which redraw a progress line.
 *
 * @internal
 */
final class Markup
{
	private const string RESET = "\033[0m";

	/**
	 * Follows a trailing backslash of escaped text, so that a tag placed
	 * right after it stays a tag: without it, `C:\` + `</red>` would read
	 * as an escaped `</red>`. escape() strips control characters from its
	 * input, so untrusted text cannot forge it; render() drops it again.
	 */
	private const string BOUNDARY = "\x1F";

	/** Stands in for a formatted argument while the markup is parsed. */
	private const string SLOT = "\x00";

	private const string CONTROLS = '/[\x00-\x08\x0B-\x1F\x7F]/';

	/** Like CONTROLS, but keeps carriage returns and the boundaries of escaped text. */
	private const string TEMPLATE_CONTROLS = '/[\x00-\x08\x0B\x0C\x0E-\x1E\x7F]/';

	/** A sprintf() conversion, `%1$s` and `%%` included. */
	private const string CONVERSION = '/%(?:%|(?:(\d+)\$)?((?:[-+ 0]|\'.)*\d*(?:\.\d+)?[bcdeEfFgGhHosuxX]))/';

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
		$names = implode('|', array_keys(self::TAGS)) . '|(?:bg-)?\#[0-9a-f]{6}';
		$this->split = "#(\\\\?</?(?:{$names})>)#";
		$this->tag = "#^\\\\?</?(?:{$names})>$#";
	}

	/**
	 * Renders the template as escape codes, or strips the markup without
	 * `$colors`. A `$style` tag encloses the whole text.
	 *
	 * @param list<float|int|string|\Stringable> $args
	 */
	public function render(string $template, array $args = [], bool $colors = false, string $style = ''): string
	{
		$text = (string) preg_replace(self::TEMPLATE_CONTROLS, replacement: '', subject: $template);
		$values = [];

		if ($args !== []) {
			[$text, $values] = $this->format($text, $args);
		}

		$parts = explode(self::SLOT, str_replace(self::BOUNDARY, '', $this->markup($text, $colors, $style)));
		$out = array_shift($parts);

		foreach ($parts as $i => $part) {
			$out .= $values[$i] . $part;
		}

		return $out;
	}

	/**
	 * Replaces the conversions with slots and formats their arguments.
	 *
	 * @param list<float|int|string|\Stringable> $args
	 *
	 * @return array{string, list<string>}
	 */
	private function format(string $template, array $args): array
	{
		$values = [];
		$next = 0;
		$text = (string) preg_replace_callback(
			self::CONVERSION,
			static function (array $match) use ($args, &$values, &$next): string {
				if ($match[0] === '%%') {
					return '%';
				}

				$index = $match[1] === '' ? $next++ : (int) $match[1] - 1;

				if (!array_key_exists($index, $args)) {
					throw new ValueError('Missing argument ' . ($index + 1) . " for '{$match[0]}'");
				}

				$values[] = (string) preg_replace(
					self::CONTROLS,
					replacement: '',
					subject: sprintf('%' . $match[2], $args[$index]),
				);

				return self::SLOT;
			},
			$template,
		);

		return [$text, $values];
	}

	private function markup(string $text, bool $colors, string $style): string
	{
		/** @var list<string> $parts */
		$parts = (array) preg_split(
			$this->split,
			$text,
			flags: PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY,
		);
		$paired = $this->paired($parts);
		$stack = $style === '' ? [] : [$style];
		$out = $colors ? $this->codes($stack) : '';

		foreach ($parts as $i => $part) {
			if (!in_array($i, $paired, strict: true)) {
				$escaped = $part[0] === '\\' && preg_match($this->tag, $part) === 1;
				$out .= $escaped ? substr($part, offset: 1) : $part;

				continue;
			}

			if ($part[1] === '/') {
				array_pop($stack);

				// A blanket reset, then re-apply the enclosing tags.
				$out .= $colors ? self::RESET . $this->codes($stack) : '';

				continue;
			}

			$name = substr($part, offset: 1, length: -1);
			$stack[] = $name;
			$out .= $colors ? $this->codes([$name]) : '';
		}

		return $colors && $style !== '' ? $out . self::RESET : $out;
	}

	/**
	 * The indexes of the tags that have a partner: a closing tag pairs
	 * with the innermost open tag if that has its name, else with none.
	 *
	 * @param list<string> $parts
	 *
	 * @return list<int>
	 */
	private function paired(array $parts): array
	{
		$paired = [];
		$open = [];

		foreach ($parts as $i => $part) {
			if ($part[0] === '\\' || preg_match($this->tag, $part) !== 1) {
				continue;
			}

			if ($part[1] !== '/') {
				$open[] = [$i, substr($part, offset: 1, length: -1)];

				continue;
			}

			$last = end($open);

			if ($last === false || $last[1] !== substr($part, offset: 2, length: -1)) {
				continue;
			}

			array_pop($open);
			array_push($paired, $last[0], $i);
		}

		return $paired;
	}

	/** @param list<string> $names */
	private function codes(array $names): string
	{
		return implode('', array_map(fn(string $name): string => "\033[" . $this->sgr($name) . 'm', $names));
	}

	/** The SGR code for a tag name: a named lookup or a truecolor hex tag. */
	private function sgr(string $name): string
	{
		if (array_key_exists($name, self::TAGS)) {
			return self::TAGS[$name];
		}

		$bg = str_starts_with($name, 'bg-');
		$hex = substr($name, offset: $bg ? 4 : 1);

		return ($bg ? '48' : '38') . ';2;' . implode(';', array_map(hexdec(...), str_split($hex, length: 2)));
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
		$text = (string) preg_replace(self::CONTROLS, replacement: '', subject: $text);
		$text = (string) preg_replace($this->split, replacement: '\\\\$0', subject: $text);

		return str_ends_with($text, '\\') ? $text . self::BOUNDARY : $text;
	}

	/**
	 * Pads the text with spaces to the visible width `$width`; wider
	 * text is returned unchanged. An uneven center split leans left.
	 */
	public function pad(string $text, int $width, Align $align): string
	{
		$missing = $width - $this->width($text);

		if ($missing <= 0) {
			return $text;
		}

		return match ($align) {
			Align::Left => $text . str_repeat(' ', $missing),
			Align::Right => str_repeat(' ', $missing) . $text,
			Align::Center => str_repeat(' ', intdiv($missing, 2))
				. $text
				. str_repeat(' ', $missing - intdiv($missing, 2)),
		};
	}

	/**
	 * The visible width of the text as rendered: tags collapse to
	 * nothing, unpaired and escaped ones print.
	 */
	public function width(string $text): int
	{
		return mb_strwidth($this->render($text));
	}
}
