<?php

declare(strict_types=1);

namespace Celema\Console\Tests;

use Celema\Console\Align;
use Celema\Console\Buffer;
use Celema\Console\Io;
use ValueError;

class IoTest extends TestCase
{
	public function testRendersMarkupWithColors(): void
	{
		$buffer = new Buffer(colors: true);
		new Io($buffer)->echo('<red>test</red>');

		$this->assertSame("\033[31mtest\033[0m", $buffer->output());
	}

	public function testStripsMarkupWithoutColors(): void
	{
		$buffer = new Buffer();
		new Io($buffer)->echo('<red>test</red>');

		$this->assertSame('test', $buffer->output());
	}

	public function testMarkupIsValidatedEvenWithColorsDisabled(): void
	{
		$this->expectException(ValueError::class);
		$this->expectExceptionMessage("Unclosed markup tag '<em>'");

		new Io(new Buffer())->echoln('<em>test');
	}

	public function testEscapeRendersTagsLiterally(): void
	{
		$buffer = new Buffer();
		$io = new Io($buffer);
		$io->echo($io->escape('keep <green>this</green> plain'));

		$this->assertSame('keep <green>this</green> plain', $buffer->output());
	}

	public function testMessageHelpersNeutralizeControlSequences(): void
	{
		$buffer = new Buffer();
		new Io($buffer)->error("evil \033]0;pwned\007 message");

		$this->assertSame('evil ]0;pwned message' . PHP_EOL, $buffer->errorOutput());
	}

	public function testMessageHelpersTreatInputAsPlainText(): void
	{
		$buffer = new Buffer();
		new Io($buffer)->error('broken </em> markup <green>included');

		$this->assertSame('broken </em> markup <green>included' . PHP_EOL, $buffer->errorOutput());
	}

	public function testMessageHelpers(): void
	{
		$buffer = new Buffer(colors: true);
		$io = new Io($buffer);
		$io->info('information');
		$io->success('succeeded');
		$io->warn('warning');
		$io->error('failed');

		$this->assertSame("information\n\033[32msucceeded\033[0m\n", $buffer->output());
		$this->assertSame("\033[33mwarning\033[0m\n\033[31mfailed\033[0m\n", $buffer->errorOutput());
	}

	public function testMessageHelpersKeepATrailingBackslash(): void
	{
		$buffer = new Buffer(colors: true);
		$io = new Io($buffer);
		$io->info('Path C:\\');
		$io->success('Path C:\\');
		$io->warn('Path C:\\');
		$io->error('Path C:\\');

		$this->assertSame("Path C:\\\n\033[32mPath C:\\\033[0m\n", $buffer->output());
		$this->assertSame("\033[33mPath C:\\\033[0m\n\033[31mPath C:\\\033[0m\n", $buffer->errorOutput());

		$plain = new Buffer();
		new Io($plain)->error('Path C:\\');

		$this->assertSame('Path C:\\' . PHP_EOL, $plain->errorOutput());
	}

	public function testErrorWritersTargetTheErrorStream(): void
	{
		$buffer = new Buffer();
		$io = new Io($buffer);
		$io->echoErr('boom ');
		$io->echolnErr('<red>bang</red>');

		$this->assertSame('', $buffer->output());
		$this->assertSame("boom bang\n", $buffer->errorOutput());
	}

	public function testColorsAreDecidedPerStream(): void
	{
		$terminal = new Fixtures\ErrorColors();
		$io = new Io($terminal);
		$io->echo('<red>out</red>');
		$io->echoErr('<red>err</red>');

		$this->assertSame([['out', false], ["\033[31merr\033[0m", true]], $terminal->writes);
	}

	public function testIndent(): void
	{
		$io = new Io(new Buffer());
		$lorem =
			'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam '
			. 'nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, '
			. 'sed diam voluptua. At vero eos et accusam et justo duo dolores et ea '
			. 'rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem '
			. 'ipsum dolor sit amet.';
		$split = explode("\n", $io->indent($lorem, 4, 40));

		$this->assertSame('    Lorem ipsum dolor sit amet,', $split[0]);
		$this->assertSame('    erat, sed diam voluptua. At vero eos', $split[4]);
	}

	public function testIndentWrapsOnTheTerminalWidth(): void
	{
		$io = new Io(new Buffer(width: 30));
		$text = 'Lorem ipsum dolor sit amet consetetur sadipscing';

		$this->assertSame("    Lorem ipsum dolor sit amet\n    consetetur sadipscing", $io->indent($text, 4));
		$this->assertSame("    Lorem ipsum dolor\n    sit amet consetetur\n    sadipscing", $io->indent($text, 4, 24));
		$this->assertSame("    Lorem ipsum dolor sit amet\n    consetetur sadipscing", $io->indent($text, 4, 30));
	}

	public function testIndentWrapsOnTheVisibleMarkupWidth(): void
	{
		$io = new Io(new Buffer());

		$this->assertSame(
			'    <green>aaa</green> bbb ccc',
			$io->indent('<green>aaa</green> bbb ccc', 4, 15),
		);
	}

	public function testIndentWrapsOnTheVisibleMultibyteWidth(): void
	{
		$io = new Io(new Buffer());

		$this->assertSame(
			"    Übersicht über\n    die",
			$io->indent('Übersicht über die', 4, 18),
		);
	}

	public function testIndentKeepsBlankLinesEmpty(): void
	{
		$io = new Io(new Buffer());

		$this->assertSame("    a\n\n    b", $io->indent("a\n\nb", 4, 40));
		$this->assertSame('', $io->indent('', 4, 40));
	}

	public function testIndentOverflowsLongWords(): void
	{
		$io = new Io(new Buffer());

		$this->assertSame(
			"    overlong-word\n    x",
			$io->indent('overlong-word x', 4, 8),
		);
	}

	public function testPadDefaultsToLeftAlignment(): void
	{
		$io = new Io(new Buffer());

		$this->assertSame('abc  ', $io->pad('abc', 5));
		$this->assertSame('  abc', $io->pad('abc', 5, Align::Right));
	}

	public function testRuleSpansTheTerminalWidth(): void
	{
		$buffer = new Buffer(width: 20);
		new Io($buffer)->rule();

		$this->assertSame(str_repeat('─', 20) . PHP_EOL, $buffer->output());
	}

	public function testRuleHonorsASingleColumn(): void
	{
		$buffer = new Buffer(width: 1);
		new Io($buffer)->rule();

		$this->assertSame('─' . PHP_EOL, $buffer->output());
	}

	public function testRuleMaxCapsTheWidth(): void
	{
		$buffer = new Buffer(width: 20);
		$io = new Io($buffer);
		$io->rule(max: 10);
		$io->rule('=', max: 40);

		$this->assertSame(str_repeat('─', 10) . PHP_EOL . str_repeat('=', 20) . PHP_EOL, $buffer->output());
	}

	public function testRuleRepeatsOnTheVisibleWidth(): void
	{
		$buffer = new Buffer(width: 5, colors: true);
		$io = new Io($buffer);
		$io->rule('<dim>─</dim>');
		$io->rule('─ ');

		$this->assertSame(
			str_repeat("\033[2m─\033[0m", 5) . PHP_EOL . '─ ─ ' . PHP_EOL,
			$buffer->output(),
		);
	}

	public function testRuleCharWithoutVisibleWidthThrows(): void
	{
		$this->expectException(ValueError::class);
		$this->expectExceptionMessage("Rule char '<dim></dim>' has no visible width");

		new Io(new Buffer())->rule('<dim></dim>');
	}

	public function testTerminalFacts(): void
	{
		$io = new Io(new Buffer(interactive: true, width: 42));

		$this->assertTrue($io->interactive());
		$this->assertSame(42, $io->width());
		$this->assertFalse(new Io(new Buffer())->interactive());
	}

	public function testAskReturnsTheTrimmedAnswer(): void
	{
		$buffer = new Buffer("  Charly  \n");

		$this->assertSame('Charly', new Io($buffer)->ask('Name?'));
		$this->assertSame('Name? ', $buffer->output());
	}

	public function testAskFallsBackToTheDefault(): void
	{
		$this->assertSame('World', new Io(new Buffer("\n"))->ask('Name?', default: 'World'));
	}

	public function testAskFallsBackToTheDefaultOnEndOfInput(): void
	{
		$this->assertSame('World', new Io(new Buffer())->ask('Name?', default: 'World'));
	}

	public function testAskHiddenKeepsWhitespaceInTheAnswer(): void
	{
		$this->assertSame('  secret pass  ', new Io(new Buffer("  secret pass  \n"))->ask('Password?', hidden: true));
	}

	public function testAskHiddenFallsBackToTheDefault(): void
	{
		$this->assertSame('none', new Io(new Buffer())->ask('Password?', 'none', hidden: true));
	}

	public function testAskReadsOneLinePerPrompt(): void
	{
		$io = new Io(new Buffer("Charly\ny\n"));

		$this->assertSame('Charly', $io->ask('Name?'));
		$this->assertTrue($io->confirm('Sure?'));
	}

	public function testChoiceReturnsTheChosenOption(): void
	{
		$buffer = new Buffer("2\n");

		$this->assertSame('staging', new Io($buffer)->choice('Environment?', ['dev', 'staging', 'prod']));
		$this->assertSame("Environment?\n  1) dev\n  2) staging\n  3) prod\n[1] ", $buffer->output());
	}

	public function testChoiceFallsBackToTheDefault(): void
	{
		$this->assertSame('dev', new Io(new Buffer("\n"))->choice('Env?', ['dev', 'prod']));
		$this->assertSame('prod', new Io(new Buffer("\n"))->choice('Env?', ['dev', 'prod'], default: 2));
		// End of input also yields the default.
		$this->assertSame('dev', new Io(new Buffer())->choice('Env?', ['dev', 'prod']));
	}

	public function testChoiceAcceptsTheFirstOptionByNumber(): void
	{
		$this->assertSame('dev', new Io(new Buffer("1\n"))->choice('Env?', ['dev', 'prod'], default: 2));
	}

	public function testChoiceReturnsTheDefaultRightAfterAnEmptyAnswer(): void
	{
		$this->assertSame('dev', new Io(new Buffer("\n2\n"))->choice('Env?', ['dev', 'prod']));
	}

	public function testChoiceAsksAgainOnInvalidAnswers(): void
	{
		// Only plain numbers count, not ones PHP would cast into range.
		$buffer = new Buffer("x\n0\n9\n+1\n1x\n1.0\n2\n");

		$this->assertSame('prod', new Io($buffer)->choice('Env?', ['dev', 'prod']));
		$this->assertStringEndsWith('[1] [1] [1] [1] [1] [1] [1] ', $buffer->output());
	}

	public function testChoiceWithoutOptionsThrows(): void
	{
		$this->expectException(ValueError::class);
		$this->expectExceptionMessage('Choice needs options');

		new Io(new Buffer())->choice('Env?', []);
	}

	public function testChoiceDefaultOutOfRangeThrows(): void
	{
		$this->expectException(ValueError::class);
		$this->expectExceptionMessage('Choice default 3 is out of range');

		new Io(new Buffer())->choice('Env?', ['dev', 'prod'], default: 3);
	}

	public function testConfirmAnswers(): void
	{
		$this->assertTrue(new Io(new Buffer("y\n"))->confirm('Sure?'));
		$this->assertTrue(new Io(new Buffer("YES\n"))->confirm('Sure?'));
		$this->assertFalse(new Io(new Buffer("n\n"))->confirm('Sure?'));
		$this->assertFalse(new Io(new Buffer("whatever\n"))->confirm('Sure?'));
	}

	public function testConfirmFallsBackToTheDefault(): void
	{
		$this->assertFalse(new Io(new Buffer("\n"))->confirm('Sure?'));
		$this->assertTrue(new Io(new Buffer("\n"))->confirm('Sure?', default: true));
	}

	public function testConfirmRendersTheDefaultInThePrompt(): void
	{
		$buffer = new Buffer("\n");
		new Io($buffer)->confirm('Sure?');

		$this->assertSame('Sure? [y/N] ', $buffer->output());

		$buffer = new Buffer("\n");
		new Io($buffer)->confirm('Sure?', default: true);

		$this->assertSame('Sure? [Y/n] ', $buffer->output());
	}
}
