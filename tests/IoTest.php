<?php

declare(strict_types=1);

namespace Celema\Console\Tests;

use Celema\Console\BufferedIo;
use Celema\Console\Io;
use RuntimeException;
use ValueError;

class IoTest extends TestCase
{
	protected function tearDown(): void
	{
		putenv('COLUMNS');
		parent::tearDown();
	}

	public function testRendersMarkupOnColoredStreams(): void
	{
		putenv('FORCE_COLOR=1');

		ob_start();
		new Io('php://output')->echo('<red>test</red>');
		$out = (string) ob_get_clean();

		$this->assertSame("\033[31mtest\033[0m", $out);
		putenv('FORCE_COLOR');
	}

	public function testHasColorSupport(): void
	{
		putenv('FORCE_COLOR=1');
		$io = new Io('php://output');

		ob_start();
		$io->echo('<red>test</red>');
		putenv('NO_COLOR=1');
		$io->echo('<red>test</red>');
		$out = (string) ob_get_clean();

		$this->assertSame("\033[31mtest\033[0mtest", $out);
		putenv('NO_COLOR');
		putenv('FORCE_COLOR');
	}

	public function testMarkupIsValidatedEvenWithColorsDisabled(): void
	{
		$this->expectException(ValueError::class);
		$this->expectExceptionMessage("Unclosed markup tag '<em>'");

		new BufferedIo()->echoln('<em>test');
	}

	public function testForceColorZeroDisablesColors(): void
	{
		putenv('FORCE_COLOR=0');
		$io = new Io('php://output');

		ob_start();
		$io->echo('<red>test</red>');
		putenv('FORCE_COLOR=false');
		$io->echo('<red>test</red>');
		$out = (string) ob_get_clean();

		$this->assertSame('testtest', $out);
		putenv('FORCE_COLOR');
	}

	public function testEmptyNoColorIsIgnored(): void
	{
		putenv('NO_COLOR=');
		putenv('FORCE_COLOR=1');

		ob_start();
		new Io('php://output')->echo('<red>test</red>');
		$out = (string) ob_get_clean();

		$this->assertSame("\033[31mtest\033[0m", $out);
		putenv('NO_COLOR');
		putenv('FORCE_COLOR');
	}

	public function testColorTermDoesNotColorRedirectedStreams(): void
	{
		putenv('NO_COLOR');
		putenv('FORCE_COLOR');
		$colorterm = getenv('COLORTERM');
		putenv('COLORTERM=truecolor');
		$out = (string) tempnam(sys_get_temp_dir(), prefix: 'cli');

		try {
			new Io($out)->success('done');

			$this->assertSame("done\n", file_get_contents($out));
		} finally {
			unlink($out);
			putenv($colorterm === false ? 'COLORTERM' : "COLORTERM={$colorterm}");
		}
	}

	public function testColorsDisabledWithoutTerminalOrEnvOverride(): void
	{
		putenv('NO_COLOR');
		putenv('FORCE_COLOR');
		$out = (string) tempnam(sys_get_temp_dir(), prefix: 'cli');
		$err = (string) tempnam(sys_get_temp_dir(), prefix: 'cli');
		$io = new Io($out, $err);
		$io->echoln('<red>regular</red>');
		$io->echolnErr('<red>error</red>');
		$stdout = (string) file_get_contents($out);
		$stderr = (string) file_get_contents($err);
		unlink($out);
		unlink($err);

		$this->assertSame("regular\n", $stdout);
		$this->assertSame("error\n", $stderr);
	}

	public function testEscapeRendersTagsLiterally(): void
	{
		$out = new BufferedIo();
		$out->echo($out->escape('keep <green>this</green> plain'));

		$this->assertSame('keep <green>this</green> plain', $out->output());
	}

	public function testMessageHelpersNeutralizeControlSequences(): void
	{
		$out = new BufferedIo();
		$out->error("evil \033]0;pwned\007 message");

		$this->assertSame('evil ]0;pwned message' . PHP_EOL, $out->errorOutput());
	}

	public function testMessageHelpersTreatInputAsPlainText(): void
	{
		$out = new BufferedIo();
		$out->error('broken </em> markup <green>included');

		$this->assertSame('broken </em> markup <green>included' . PHP_EOL, $out->errorOutput());
	}

	public function testIndent(): void
	{
		putenv('COLUMNS');
		$io = new Io('php://output');
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

	public function testIndentWrapsOnTheVisibleMarkupWidth(): void
	{
		$io = new Io('php://output');

		$this->assertSame(
			'    <green>aaa</green> bbb ccc',
			$io->indent('<green>aaa</green> bbb ccc', 4, 15),
		);
	}

	public function testIndentWrapsOnTheVisibleMultibyteWidth(): void
	{
		$io = new Io('php://output');

		$this->assertSame(
			"    Übersicht über\n    die",
			$io->indent('Übersicht über die', 4, 18),
		);
	}

	public function testIndentKeepsBlankLinesEmpty(): void
	{
		$io = new Io('php://output');

		$this->assertSame("    a\n\n    b", $io->indent("a\n\nb", 4, 40));
		$this->assertSame('', $io->indent('', 4, 40));
	}

	public function testIndentOverflowsLongWords(): void
	{
		$io = new Io('php://output');

		$this->assertSame(
			"    overlong-word\n    x",
			$io->indent('overlong-word x', 4, 8),
		);
	}

	public function testIndentUsesColumnsEnvAndCaches(): void
	{
		putenv('COLUMNS=40');
		$io = new Io('php://output');
		$text = 'Lorem ipsum dolor sit amet consetetur sadipscing';

		$first = explode("\n", $io->indent($text, 4));
		// The second call is served from the cached width.
		$second = explode("\n", $io->indent($text, 4));

		$this->assertSame($first, $second);
		$this->assertSame('    Lorem ipsum dolor sit amet', $first[0]);
	}

	public function testMessageHelpers(): void
	{
		putenv('FORCE_COLOR=1');
		$io = new Io('php://output', 'php://output');

		ob_start();
		$io->info('information');
		$io->success('succeeded');
		$io->warn('warning');
		$io->error('failed');
		$result = (string) ob_get_clean();

		$this->assertStringContainsString("information\n", $result);
		$this->assertStringContainsString("\033[32msucceeded\033[0m\n", $result);
		$this->assertStringContainsString("\033[33mwarning\033[0m\n", $result);
		$this->assertStringContainsString("\033[31mfailed\033[0m\n", $result);
		putenv('FORCE_COLOR');
	}

	public function testMessageHelperStreams(): void
	{
		putenv('NO_COLOR=1');
		$err = (string) tempnam(sys_get_temp_dir(), prefix: 'cli');
		$io = new Io('php://output', $err);

		ob_start();
		$io->info('information');
		$io->success('succeeded');
		$io->warn('warning');
		$io->error('failed');
		$stdout = (string) ob_get_clean();

		$contents = (string) file_get_contents($err);
		unlink($err);

		$this->assertSame("information\nsucceeded\n", $stdout);
		$this->assertSame("warning\nfailed\n", $contents);
		putenv('NO_COLOR');
	}

	public function testErrorWritersTargetTheErrorStream(): void
	{
		$err = (string) tempnam(sys_get_temp_dir(), prefix: 'cli');
		$io = new Io('php://output', $err);

		ob_start();
		$io->echoErr('boom');
		$io->echolnErr('<red>bang</red>');
		$stdout = (string) ob_get_clean();

		$contents = (string) file_get_contents($err);
		unlink($err);

		$this->assertSame('', $stdout);
		$this->assertStringContainsString('boom', $contents);
		$this->assertStringContainsString('bang', $contents);
	}

	public function testAskReadsFromTheInputTarget(): void
	{
		$in = (string) tempnam(sys_get_temp_dir(), prefix: 'cli');
		file_put_contents($in, data: "Charly\n");
		$io = new Io('php://output', inputTarget: $in);

		ob_start();
		$answer = $io->ask('Name?');
		ob_end_clean();
		unlink($in);

		$this->assertSame('Charly', $answer);
	}

	public function testAskReturnsTheTrimmedAnswer(): void
	{
		$out = new BufferedIo("  Charly  \n");

		$this->assertSame('Charly', $out->ask('Name?'));
		$this->assertSame('Name? ', $out->output());
	}

	public function testAskFallsBackToTheDefault(): void
	{
		$out = new BufferedIo("\n");

		$this->assertSame('World', $out->ask('Name?', default: 'World'));
	}

	public function testAskFallsBackToTheDefaultOnEndOfInput(): void
	{
		$out = new BufferedIo();

		$this->assertSame('World', $out->ask('Name?', default: 'World'));
	}

	public function testAskHiddenReadsPlainlyWithoutTerminal(): void
	{
		$out = new BufferedIo("secret\n");

		$this->assertSame('secret', $out->ask('Password?', hidden: true));
	}

	public function testAskHiddenKeepsWhitespaceInTheAnswer(): void
	{
		$out = new BufferedIo("  secret pass  \n");

		$this->assertSame('  secret pass  ', $out->ask('Password?', hidden: true));
	}

	public function testUnopenableTargetThrows(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage("Could not open stream '/nonexistent/dir/out'");

		new Io('/nonexistent/dir/out')->echo('test');
	}

	public function testAskReadsOneLinePerPrompt(): void
	{
		$out = new BufferedIo("Charly\ny\n");

		$this->assertSame('Charly', $out->ask('Name?'));
		$this->assertTrue($out->confirm('Sure?'));
	}

	public function testConfirmAnswers(): void
	{
		$this->assertTrue(new BufferedIo("y\n")->confirm('Sure?'));
		$this->assertTrue(new BufferedIo("YES\n")->confirm('Sure?'));
		$this->assertFalse(new BufferedIo("n\n")->confirm('Sure?'));
		$this->assertFalse(new BufferedIo("whatever\n")->confirm('Sure?'));
	}

	public function testConfirmFallsBackToTheDefault(): void
	{
		$this->assertFalse(new BufferedIo("\n")->confirm('Sure?'));
		$this->assertTrue(new BufferedIo("\n")->confirm('Sure?', default: true));
	}

	public function testConfirmRendersTheDefaultInThePrompt(): void
	{
		$out = new BufferedIo("\n");
		$out->confirm('Sure?');

		$this->assertSame('Sure? [y/N] ', $out->output());

		$out = new BufferedIo("\n");
		$out->confirm('Sure?', default: true);

		$this->assertSame('Sure? [Y/n] ', $out->output());
	}
}
