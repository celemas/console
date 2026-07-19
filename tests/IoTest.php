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

	public function testForegroundColors(): void
	{
		putenv('FORCE_COLOR=1');
		$io = new Io('php://output');

		$this->assertSame("\033[0;30mtest\033[0m", $io->color('test', 'black'));
		$this->assertSame("\033[1;30mtest\033[0m", $io->color('test', 'gray'));
		$this->assertSame("\033[1;30mtest\033[0m", $io->color('test', 'grey'));
		$this->assertSame("\033[0;31mtest\033[0m", $io->color('test', 'red'));
		$this->assertSame("\033[1;31mtest\033[0m", $io->color('test', 'lightred'));
		$this->assertSame("\033[0;32mtest\033[0m", $io->color('test', 'green'));
		$this->assertSame("\033[1;32mtest\033[0m", $io->color('test', 'lightgreen'));
		$this->assertSame("\033[0;33mtest\033[0m", $io->color('test', 'brown'));
		$this->assertSame("\033[1;33mtest\033[0m", $io->color('test', 'yellow'));
		$this->assertSame("\033[0;34mtest\033[0m", $io->color('test', 'blue'));
		$this->assertSame("\033[1;34mtest\033[0m", $io->color('test', 'lightblue'));
		$this->assertSame("\033[0;35mtest\033[0m", $io->color('test', 'magenta'));
		$this->assertSame("\033[1;35mtest\033[0m", $io->color('test', 'lightmagenta'));
		$this->assertSame("\033[0;35mtest\033[0m", $io->color('test', 'purple'));
		$this->assertSame("\033[1;35mtest\033[0m", $io->color('test', 'lightpurple'));
		$this->assertSame("\033[0;36mtest\033[0m", $io->color('test', 'cyan'));
		$this->assertSame("\033[1;36mtest\033[0m", $io->color('test', 'lightcyan'));
		$this->assertSame("\033[0;37mtest\033[0m", $io->color('test', 'lightgray'));
		$this->assertSame("\033[0;37mtest\033[0m", $io->color('test', 'lightgrey'));
		$this->assertSame("\033[1;37mtest\033[0m", $io->color('test', 'white'));
		$this->assertSame('test', $io->color('test'));
		putenv('FORCE_COLOR');
	}

	public function testHasColorSupport(): void
	{
		putenv('FORCE_COLOR=1');
		$io = new Io('php://output');
		$this->assertSame("\033[0;31mtest\033[0m", $io->color('test', 'red'));
		putenv('NO_COLOR=1');
		$this->assertSame('test', $io->color('test', 'red'));
		putenv('NO_COLOR');
		putenv('FORCE_COLOR');
	}

	public function testUnknownColorThrows(): void
	{
		$this->expectException(ValueError::class);
		$this->expectExceptionMessage("Unknown color 'gren'");

		new Io('php://output')->color('test', 'gren');
	}

	public function testUnknownBackgroundColorThrows(): void
	{
		$this->expectException(ValueError::class);
		$this->expectExceptionMessage("Unknown background color 'gren'");

		new Io('php://output')->color('test', background: 'gren');
	}

	public function testColorNamesAreValidatedEvenWithColorsDisabled(): void
	{
		$this->expectException(ValueError::class);

		new BufferedIo()->echoln('test', 'gren');
	}

	public function testForceColorZeroDisablesColors(): void
	{
		putenv('FORCE_COLOR=0');
		$io = new Io('php://output');
		$this->assertSame('test', $io->color('test', 'red'));

		putenv('FORCE_COLOR=false');
		$this->assertSame('test', $io->color('test', 'red'));
		putenv('FORCE_COLOR');
	}

	public function testEmptyNoColorIsIgnored(): void
	{
		putenv('NO_COLOR=');
		putenv('FORCE_COLOR=1');
		$io = new Io('php://output');

		$this->assertSame("\033[0;31mtest\033[0m", $io->color('test', 'red'));
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
		$io->echoln('regular', 'red');
		$io->echolnErr('error', 'red');
		$stdout = (string) file_get_contents($out);
		$stderr = (string) file_get_contents($err);
		unlink($out);
		unlink($err);

		$this->assertSame("regular\n", $stdout);
		$this->assertSame("error\n", $stderr);
	}

	public function testBackgroundColors(): void
	{
		putenv('FORCE_COLOR=1');
		$io = new Io('php://output');

		$this->assertSame("\033[0;37;40mtest\033[0m", $io->color('test', 'lightgrey', 'black'));
		$this->assertSame("\033[1;37;41mtest\033[0m", $io->color('test', 'white', 'red'));
		$this->assertSame("\033[1;32;42mtest\033[0m", $io->color('test', 'lightgreen', 'green'));
		$this->assertSame("\033[1;33;43mtest\033[0m", $io->color('test', 'yellow', 'yellow'));
		$this->assertSame("\033[0;34;44mtest\033[0m", $io->color('test', 'blue', 'blue'));
		$this->assertSame("\033[1;35;45mtest\033[0m", $io->color('test', 'lightpurple', 'purple'));
		$this->assertSame("\033[0;35;45mtest\033[0m", $io->color('test', 'purple', 'magenta'));
		$this->assertSame("\033[0;36;46mtest\033[0m", $io->color('test', 'cyan', 'cyan'));
		$this->assertSame("\033[1;37;47mtest\033[0m", $io->color('test', 'white', 'white'));
		$this->assertSame("\033[1;37;47mtest\033[0m", $io->color('test', 'white', 'gray'));
		$this->assertSame("\033[1;37;47mtest\033[0m", $io->color('test', 'white', 'grey'));

		$this->assertSame("\033[40mtest\033[0m", $io->color('test', background: 'black'));
		$this->assertSame("\033[41mtest\033[0m", $io->color('test', background: 'red'));
		$this->assertSame("\033[42mtest\033[0m", $io->color('test', background: 'green'));
		$this->assertSame("\033[43mtest\033[0m", $io->color('test', background: 'yellow'));
		$this->assertSame("\033[44mtest\033[0m", $io->color('test', background: 'blue'));
		$this->assertSame("\033[45mtest\033[0m", $io->color('test', background: 'purple'));
		$this->assertSame("\033[45mtest\033[0m", $io->color('test', background: 'magenta'));
		$this->assertSame("\033[46mtest\033[0m", $io->color('test', background: 'cyan'));
		$this->assertSame("\033[47mtest\033[0m", $io->color('test', background: 'white'));
		$this->assertSame("\033[47mtest\033[0m", $io->color('test', background: 'gray'));
		$this->assertSame("\033[47mtest\033[0m", $io->color('test', background: 'grey'));
		putenv('FORCE_COLOR');
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

		$this->assertSame('    Lorem ipsum dolor sit amet, consetetur', $split[0]);
		$this->assertSame('    At vero eos et accusam et justo duo', $split[4]);
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
		$this->assertStringContainsString("\033[0;32msucceeded\033[0m\n", $result);
		$this->assertStringContainsString("\033[1;33mwarning\033[0m\n", $result);
		$this->assertStringContainsString("\033[0;31mfailed\033[0m\n", $result);
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
		$io->echolnErr('bang', 'red');
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
