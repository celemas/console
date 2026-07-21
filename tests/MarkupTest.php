<?php

declare(strict_types=1);

namespace Celema\Console\Tests;

use Celema\Console\Markup;
use PHPUnit\Framework\Attributes\DataProvider;
use ValueError;

class MarkupTest extends TestCase
{
	private function render(string $text, bool $colors = true): string
	{
		return new Markup()->render($text, $colors);
	}

	public static function tagProvider(): array
	{
		return [
			['strong', '1'],
			['em', '3'],
			['dim', '2'],
			['u', '4'],
			['black', '30'],
			['red', '31'],
			['green', '32'],
			['yellow', '33'],
			['blue', '34'],
			['magenta', '35'],
			['cyan', '36'],
			['white', '37'],
			['gray', '90'],
			['bright-black', '90'],
			['bright-red', '91'],
			['bright-green', '92'],
			['bright-yellow', '93'],
			['bright-blue', '94'],
			['bright-magenta', '95'],
			['bright-cyan', '96'],
			['bright-white', '97'],
			['bg-black', '40'],
			['bg-red', '41'],
			['bg-green', '42'],
			['bg-yellow', '43'],
			['bg-blue', '44'],
			['bg-magenta', '45'],
			['bg-cyan', '46'],
			['bg-white', '47'],
			['bg-gray', '100'],
			['bg-bright-black', '100'],
			['bg-bright-red', '101'],
			['bg-bright-green', '102'],
			['bg-bright-yellow', '103'],
			['bg-bright-blue', '104'],
			['bg-bright-magenta', '105'],
			['bg-bright-cyan', '106'],
			['bg-bright-white', '107'],
			['#ff7313', '38;2;255;115;19'],
			['#000000', '38;2;0;0;0'],
			['bg-#ff7313', '48;2;255;115;19'],
		];
	}

	#[DataProvider('tagProvider')]
	public function testRendersTag(string $tag, string $code): void
	{
		$this->assertSame(
			"\033[{$code}mtest\033[0m",
			$this->render("<{$tag}>test</{$tag}>"),
		);
	}

	#[DataProvider('tagProvider')]
	public function testStripsTagWithoutColors(string $tag, string $code): void
	{
		$this->assertSame('test', $this->render("<{$tag}>test</{$tag}>", colors: false));
	}

	public function testPlainTextPassesThrough(): void
	{
		$this->assertSame('plain text', $this->render('plain text'));
	}

	public function testUnknownTagsPassThrough(): void
	{
		$text = 'Mail <info@example.com>, a <foo>generic</foo>, list<u8>, 4 < 5';

		$this->assertSame($text, $this->render($text));
		$this->assertSame($text, $this->render($text, colors: false));
	}

	public function testNestedTagsRestoreTheEnclosingStyle(): void
	{
		$this->assertSame(
			"\033[3ma \033[32mb\033[0m\033[3m c\033[0m",
			$this->render('<em>a <green>b</green> c</em>'),
		);
		$this->assertSame(
			"\033[38;2;255;115;19ma \033[32mb\033[0m\033[38;2;255;115;19m c\033[0m",
			$this->render('<#ff7313>a <green>b</green> c</#ff7313>'),
		);
	}

	public function testInvalidHexTagsPassThrough(): void
	{
		$text = 'a <#FF7313>b</#FF7313>, <#f73>c</#f73>, <#ff73134>d, <bg#ff7313>e';

		$this->assertSame($text, $this->render($text));
		$this->assertSame($text, $this->render($text, colors: false));
	}

	public function testInnermostTagWinsOnConflict(): void
	{
		$this->assertSame(
			"\033[32ma\033[31mb\033[0m\033[32ma\033[0m",
			$this->render('<green>a<red>b</red>a</green>'),
		);
	}

	public function testForegroundAndBackgroundCompose(): void
	{
		$this->assertSame(
			"\033[97m\033[41malert\033[0m\033[97m\033[0m",
			$this->render('<bright-white><bg-red>alert</bg-red></bright-white>'),
		);
	}

	public function testEscapedTagRendersLiterally(): void
	{
		$this->assertSame('a <green>b</green>', $this->render('a \<green>b\</green>'));
		$this->assertSame('a <green>b</green>', $this->render('a \<green>b\</green>', colors: false));
	}

	public function testDanglingClosingTagThrows(): void
	{
		$this->expectException(ValueError::class);
		$this->expectExceptionMessage("Markup tag '</green>' has no opening tag");

		$this->render('a</green>');
	}

	public function testMismatchedClosingTagThrows(): void
	{
		$this->expectException(ValueError::class);
		$this->expectExceptionMessage("Markup tag '<em>' closed by '</green>'");

		$this->render('<em>a</green>');
	}

	public function testUnclosedTagThrows(): void
	{
		$this->expectException(ValueError::class);
		$this->expectExceptionMessage("Unclosed markup tag '<green>'");

		$this->render('<em><green>a');
	}

	public function testValidatesWithoutColors(): void
	{
		$this->expectException(ValueError::class);
		$this->expectExceptionMessage("Unclosed markup tag '<em>'");

		$this->render('<em>a', colors: false);
	}

	public function testEscapePrefixesKnownTags(): void
	{
		$markup = new Markup();

		$this->assertSame('\<green>a\</green>', $markup->escape('<green>a</green>'));
		$this->assertSame('\<#ff7313>a\</bg-#ff7313>', $markup->escape('<#ff7313>a</bg-#ff7313>'));
		$this->assertSame('<foo> stays', $markup->escape('<foo> stays'));
	}

	public function testWidthCountsOnlyVisibleCharacters(): void
	{
		$markup = new Markup();

		$this->assertSame(3, $markup->width('abc'));
		$this->assertSame(3, $markup->width('<green>abc</green>'));
		$this->assertSame(3, $markup->width('<#ff7313>abc</#ff7313>'));
		$this->assertSame(7, $markup->width('\<green>'));
		$this->assertSame(9, $markup->width('Übersicht'));
		$this->assertSame(5, $markup->width('<foo>'));
	}

	public function testEscapeStripsControlCharacters(): void
	{
		$markup = new Markup();

		// The control bytes are removed; the harmless payload stays text.
		$this->assertSame(']0;eviltitle', $markup->escape("\033]0;evil\007title"));
		$this->assertSame("keep\nlines\tandtabs", $markup->escape("keep\nlines\tand\rtabs\x7f"));
	}

	public function testEscapedTextRendersAsItsInput(): void
	{
		$markup = new Markup();
		$text = 'broken </em> markup <green>and\<red> escapes';

		$this->assertSame($text, $markup->render($markup->escape($text), colors: true));
	}
}
