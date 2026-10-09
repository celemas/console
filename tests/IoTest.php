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
		new Io($buffer)->write('<red>test</red>');

		$this->assertSame("\033[31mtest\033[0m", $buffer->output());
	}

	public function testStripsMarkupWithoutColors(): void
	{
		$buffer = new Buffer();
		new Io($buffer)->write('<red>test</red>');

		$this->assertSame('test', $buffer->output());
	}

	public function testLineEndsTheLine(): void
	{
		$buffer = new Buffer();
		$io = new Io($buffer);
		$io->line('one');
		$io->line();
		$io->write('two');

		$this->assertSame("one\n\ntwo", $buffer->output());
	}

	public function testArgumentsFillTheTemplate(): void
	{
		$buffer = new Buffer(colors: true);
		$io = new Io($buffer);
		$io->line('<green>%s</green> holds %d items, %05.1f%% full', 'Cart', 3, 42.25);
		$io->line('%2$s %1$s', 'world', 'hello');

		$this->assertSame("\033[32mCart\033[0m holds 3 items, 042.2% full\nhello world\n", $buffer->output());
	}

	public function testNamedArgumentsFillTheTemplateInOrder(): void
	{
		$buffer = new Buffer();
		new Io($buffer)->line('%s-%s', first: 'a', second: 'b');

		$this->assertSame("a-b\n", $buffer->output());
	}

	public function testArgumentsPrintAsPlainText(): void
	{
		$buffer = new Buffer(colors: true);
		new Io($buffer)->line('Path: <strong>%s</strong>', '<red>C:\\</red>');

		$this->assertSame("Path: \033[1m<red>C:\\</red>\033[0m\n", $buffer->output());
	}

	public function testControlCharactersAreDropped(): void
	{
		$buffer = new Buffer();
		$io = new Io($buffer);
		$io->line('%s', "evil \033]0;pwned\007 message");
		$io->line("raw \033[31mred\tand\x7f tab");
		$io->line('%s', "spoofed\rline");

		$this->assertSame("evil ]0;pwned message\nraw [31mred\tand tab\nspoofedline\n", $buffer->output());
	}

	public function testTemplatesKeepCarriageReturns(): void
	{
		$buffer = new Buffer();
		new Io($buffer)->write("\r%3d%%", 50);

		$this->assertSame("\r 50%", $buffer->output());
	}

	public function testTemplateWithoutArgumentsIsNotFormatted(): void
	{
		$buffer = new Buffer();
		new Io($buffer)->line('100% of %s and %%');

		$this->assertSame("100% of %s and %%\n", $buffer->output());
	}

	public function testMissingArgumentThrows(): void
	{
		$this->expectException(ValueError::class);
		$this->expectExceptionMessage("Missing argument 2 for '%d'");

		new Io(new Buffer())->line('%s has %d', 'Cart');
	}

	public function testMissingPositionalArgumentThrows(): void
	{
		$this->expectException(ValueError::class);
		$this->expectExceptionMessage("Missing argument 3 for '%3\$s'");

		new Io(new Buffer())->line('%1$s %3$s', 'a', 'b');
	}

	public function testUnpairedTagsPrintLiterally(): void
	{
		$buffer = new Buffer(colors: true);
		new Io($buffer)->line('broken </em> markup <green>included');

		$this->assertSame("broken </em> markup <green>included\n", $buffer->output());
	}

	public function testEscapeRendersTagsLiterally(): void
	{
		$buffer = new Buffer();
		$io = new Io($buffer);
		$io->write('<green>' . $io->escape('keep <green>this</green> plain') . '</green>');

		$this->assertSame('keep <green>this</green> plain', $buffer->output());
	}

	public function testMessageHelpers(): void
	{
		$buffer = new Buffer(colors: true);
		$io = new Io($buffer);
		$io->success('succeeded');
		$io->warn('warning');
		$io->error('failed');

		$this->assertSame("\033[32msucceeded\033[0m\n", $buffer->output());
		$this->assertSame("\033[33mwarning\033[0m\n\033[31mfailed\033[0m\n", $buffer->errorOutput());
	}

	public function testMessageHelpersStyleAroundTheTemplate(): void
	{
		$buffer = new Buffer(colors: true);
		new Io($buffer)->success('Created <strong>%d</strong> files', 3);

		$this->assertSame("\033[32mCreated \033[1m3\033[0m\033[32m files\033[0m\n", $buffer->output());
	}

	public function testMessageHelpersWithoutColors(): void
	{
		$buffer = new Buffer();
		$io = new Io($buffer);
		$io->success('Saved <strong>%s</strong>', 'a.txt');
		$io->warn('Skipped %s', 'b.txt');
		$io->error('Cannot read <em>%s', 'c.txt');

		$this->assertSame("Saved a.txt\n", $buffer->output());
		$this->assertSame("Skipped b.txt\nCannot read <em>c.txt\n", $buffer->errorOutput());
	}

	public function testMessageHelpersKeepATrailingBackslash(): void
	{
		$buffer = new Buffer(colors: true);
		$io = new Io($buffer);
		$io->success('Path C:\\');
		$io->error('Path %s', 'C:\\');

		$this->assertSame("\033[32mPath C:\\\033[0m\n", $buffer->output());
		$this->assertSame("\033[31mPath C:\\\033[0m\n", $buffer->errorOutput());
	}

	public function testColorsAreDecidedPerStream(): void
	{
		$terminal = new Fixtures\Recorder();
		$io = new Io($terminal);
		$io->write('<red>out</red>');
		$io->error('err');

		$this->assertSame([['out', false], ["\033[31merr\033[0m\n", true]], $terminal->writes);
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

	public function testAskShowsAndFallsBackToTheDefault(): void
	{
		$buffer = new Buffer("\n");

		$this->assertSame('World', new Io($buffer)->ask('Name?', 'World'));
		$this->assertSame('Name? [World] ', $buffer->output());
	}

	public function testAskShowsTheDefaultAsPlainText(): void
	{
		$buffer = new Buffer(colors: true);
		new Io($buffer)->ask('<em>Tag?</em>', '<red>%s</red>');

		$this->assertSame("\033[3mTag?\033[0m [<red>%s</red>] ", $buffer->output());
	}

	public function testAskFallsBackToTheDefaultOnEndOfInput(): void
	{
		$this->assertSame('World', new Io(new Buffer())->ask('Name?', default: 'World'));
	}

	public function testAskReadsVisibly(): void
	{
		$terminal = new Fixtures\Recorder(['Charly']);
		new Io($terminal)->ask('Name?');

		$this->assertSame([false], $terminal->reads);
	}

	public function testAskReadsOneLinePerPrompt(): void
	{
		$io = new Io(new Buffer("Charly\ny\n"));

		$this->assertSame('Charly', $io->ask('Name?'));
		$this->assertTrue($io->confirm('Sure?'));
	}

	public function testSecretReadsHidden(): void
	{
		$terminal = new Fixtures\Recorder(['  secret pass  ']);

		$this->assertSame('  secret pass  ', new Io($terminal)->secret('Password:'));
		$this->assertSame([true], $terminal->reads);
		$this->assertSame([['Password: ', false]], $terminal->writes);
	}

	public function testSecretIsEmptyAtTheEndOfInput(): void
	{
		$this->assertSame('', new Io(new Buffer())->secret('Password:'));
	}

	public function testChoiceReturnsTheChosenKey(): void
	{
		$buffer = new Buffer("2\n");
		$options = ['dev' => 'Development', 'staging' => 'Staging', 'prod' => 'Production'];

		$this->assertSame('staging', new Io($buffer)->choice('Environment?', $options));
		$this->assertSame(
			"Environment?\n  1) Development\n  2) Staging\n  3) Production\n[1] ",
			$buffer->output(),
		);
	}

	public function testChoiceReturnsTheIndexOfAList(): void
	{
		$this->assertSame(2, new Io(new Buffer("3\n"))->choice('Env?', ['dev', 'staging', 'prod']));
	}

	public function testChoiceListsTheLabelsAsPlainText(): void
	{
		$buffer = new Buffer("\n", colors: true);
		new Io($buffer)->choice('Pick', ['a' => '<red>%s</red>']);

		$this->assertSame("Pick\n  1) <red>%s</red>\n[1] ", $buffer->output());
	}

	public function testChoiceFallsBackToTheDefault(): void
	{
		$options = ['dev' => 'Development', 'prod' => 'Production'];

		$this->assertSame('dev', new Io(new Buffer("\n"))->choice('Env?', $options));
		// End of input also yields the default.
		$this->assertSame('dev', new Io(new Buffer())->choice('Env?', $options));
	}

	public function testChoiceDefaultsByKey(): void
	{
		$buffer = new Buffer("\n");

		$this->assertSame('prod', new Io($buffer)->choice('Env?', ['dev' => 'D', 'prod' => 'P'], default: 'prod'));
		$this->assertStringEndsWith('[2] ', $buffer->output());
		$this->assertSame(1, new Io(new Buffer("\n"))->choice('Env?', [1 => 'a', 2 => 'b'], default: '1'));
	}

	public function testChoiceAcceptsTheFirstOptionByNumber(): void
	{
		$this->assertSame(0, new Io(new Buffer("1\n"))->choice('Env?', ['dev', 'prod'], default: 1));
	}

	public function testChoiceReturnsTheDefaultRightAfterAnEmptyAnswer(): void
	{
		$this->assertSame(0, new Io(new Buffer("\n2\n"))->choice('Env?', ['dev', 'prod']));
	}

	public function testChoiceAsksAgainOnInvalidAnswers(): void
	{
		// Only plain numbers count, not ones PHP would cast into range.
		$buffer = new Buffer("x\n0\n9\n+1\n1x\n1.0\n2\n");

		$this->assertSame(1, new Io($buffer)->choice('Env?', ['dev', 'prod']));
		$this->assertStringEndsWith('[1] [1] [1] [1] [1] [1] [1] ', $buffer->output());
	}

	public function testChoiceWithoutOptionsThrows(): void
	{
		$this->expectException(ValueError::class);
		$this->expectExceptionMessage('Choice needs options');

		new Io(new Buffer())->choice('Env?', []);
	}

	public function testChoiceDefaultThatIsNoOptionThrows(): void
	{
		$this->expectException(ValueError::class);
		$this->expectExceptionMessage("Choice default 'test' is not an option");

		new Io(new Buffer())->choice('Env?', ['dev' => 'D', 'prod' => 'P'], default: 'test');
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
