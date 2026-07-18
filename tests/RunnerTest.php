<?php

declare(strict_types=1);

namespace Celemas\Cli\Tests;

use Celemas\Cli\Commands;
use Celemas\Cli\Runner;

class RunnerTest extends TestCase
{
	public function testShowHelpWhenCalledWithoutCommand(): void
	{
		$_SERVER['argv'] = ['run'];
		$runner = $this->getRunner();

		$this->expectOutputRegex("/available commands.*bar.*prints foo's stuff/si");
		$runner->run();
	}

	public function testShowHelpWhenCalledWithHelpCommand(): void
	{
		$_SERVER['argv'] = ['run', 'help'];
		$runner = $this->getRunner();

		$this->expectOutputRegex("/available commands.*prints bar's stuff.*foo/si");
		$runner->run();
	}

	public function testListCommands(): void
	{
		$_SERVER['argv'] = ['run', 'commands'];
		$runner = $this->getRunner();

		$this->expectOutputString("bar:stuff\ndrivel\nerr\nerr:err\nfoo:drivel\nfoo:stuff\n");
		$runner->run();
	}

	public function testShowCommandSpecificHelp(): void
	{
		$_SERVER['argv'] = ['run', 'help', 'foo:stuff'];
		$runner = $this->getRunner();

		$this->expectOutputRegex('/php run foo:stuff.*Options:.*Lorem ipsum/s');
		$runner->run();
	}

	public function testCommandSpecificHelpDefault(): void
	{
		$_SERVER['argv'] = ['run', 'help', 'bar:stuff'];
		$runner = $this->getRunner();

		$this->expectOutputRegex('/php run bar:stuff/');
		$runner->run();
	}

	public function testShowHelpInOrder(): void
	{
		$_SERVER['argv'] = ['run'];
		$runner = $this->getRunner();

		$this->expectOutputRegex(
			'/Available.*Bar.*bar:.*stuff.*Errors.*err:.*err.*Foo.*foo:.*drivel.*stuff/s',
		);
		$runner->run();
	}

	public function testRunSimpleCommand(): void
	{
		$_SERVER['argv'] = ['run', 'drivel'];
		$runner = $this->getRunner();

		$this->expectOutputString("Foo's drivel");
		$runner->run();
	}

	public function testRunAmbiguousCommand(): void
	{
		$_SERVER['argv'] = ['run', 'stuff'];
		$runner = $this->getRunner();

		$this->expectOutputRegex('/Ambiguous.*bar.*:stuff.*foo.*:stuff/s');
		$runner->run();
	}

	public function testRunGroupNameCommand(): void
	{
		$_SERVER['argv'] = ['run', 'bar:stuff'];
		$runner = $this->getRunner();

		$this->expectOutputString("Bar's stuff");
		$runner->run();
	}

	public function testRunUnknownCommand(): void
	{
		$_SERVER['argv'] = ['run', 'unknown'];
		$runner = $this->getRunner();

		$this->expectOutputRegex('/Command not found/');
		$runner->run();
	}

	public function testRunUnknownGroupCommand(): void
	{
		$_SERVER['argv'] = ['run', 'foo:unknown'];
		$runner = $this->getRunner();

		$this->expectOutputRegex('/Command not found/');
		$runner->run();
	}

	public function testRunCommandWithExtraColonsNotFound(): void
	{
		$_SERVER['argv'] = ['run', 'foo:stuff:extra'];
		$runner = $this->getRunner();

		$this->expectOutputRegex('/Command not found/');
		$runner->run();
	}

	public function testUngroupedCommandsShareSingleGeneralHeader(): void
	{
		$_SERVER['argv'] = ['run'];
		$runner = new Runner(new Commands([new Fixtures\Plain(), new Fixtures\BarStuff()]));

		ob_start();
		$runner->run();
		$raw = (string) ob_get_clean();
		$out = (string) preg_replace('/\033\[[0-9;]*m/', replacement: '', subject: $raw);

		$this->assertSame(1, substr_count($out, needle: 'General'));
		$this->assertMatchesRegularExpression('/General.*commands.*help.*plain/s', $out);
		$this->assertStringContainsString('bar:stuff', $out);
	}

	public function testRunFailingCommand(): void
	{
		$_SERVER['argv'] = ['run', 'err'];
		$runner = $this->getRunner();

		$this->expectOutputRegex("/Error while.*'err'.*Red herring/s");
		$runner->run();
	}

	public function testRunFailingCommandWithCustomPrefix(): void
	{
		$_SERVER['argv'] = ['run', 'err:err'];
		$runner = $this->getRunner();

		$this->expectOutputRegex("/Error while.*'err:err'.*Red herring/s");
		$runner->run();
	}

	public function testRunFailingCommandWithDebug(): void
	{
		$_SERVER['argv'] = ['run', 'err'];
		$runner = new Runner(
			$this->getCommands(),
			output: 'php://output',
			errorOutput: 'php://output',
			debug: true,
		);

		$this->expectOutputRegex("/Error while.*'err'.*Red herring.*Traceback:/s");
		$runner->run();
	}

	public function testRunReturnsSuccessCode(): void
	{
		$_SERVER['argv'] = ['run', 'drivel'];

		ob_start();
		$code = $this->getRunner()->run();
		ob_get_clean();

		$this->assertSame(0, $code);
	}

	public function testRunReturnsFailureCodeFromCommand(): void
	{
		$_SERVER['argv'] = ['run', 'fail'];
		$runner = new Runner(new Commands([new Fixtures\Failing()]));

		$this->assertSame(1, $runner->run());
	}

	public function testRunReturnsFailureCodeOnException(): void
	{
		$_SERVER['argv'] = ['run', 'err'];

		ob_start();
		$code = $this->getRunner()->run();
		ob_get_clean();

		$this->assertSame(1, $code);
	}

	public function testErrorsGoToStderrNotStdout(): void
	{
		$_SERVER['argv'] = ['run', 'unknown'];
		$err = (string) tempnam(sys_get_temp_dir(), prefix: 'cli');
		$runner = new Runner($this->getCommands(), output: 'php://output', errorOutput: $err);

		ob_start();
		$code = $runner->run();
		$stdout = (string) ob_get_clean();

		$contents = (string) file_get_contents($err);
		unlink($err);

		$this->assertSame(1, $code);
		$this->assertSame('', $stdout);
		$this->assertStringContainsString('Command not found', $contents);
	}

	public function testCommandReceivesParsedArgs(): void
	{
		$_SERVER['argv'] = ['run', 'greet', 'Ada', '--greeting=Hi'];
		$runner = new Runner(new Commands([new Fixtures\Greet()]));

		$this->expectOutputString('Hi, Ada');
		$runner->run();
	}

	public function testCommandUsesArgDefaults(): void
	{
		$_SERVER['argv'] = ['run', 'greet'];
		$runner = new Runner(new Commands([new Fixtures\Greet()]));

		$this->expectOutputString('Hello, World');
		$runner->run();
	}

	public function testHelpFlagsAreNotReserved(): void
	{
		$_SERVER['argv'] = ['run', 'greet', '--help'];
		$runner = new Runner(new Commands([new Fixtures\Greet()]));

		// The runner no longer intercepts --help/-h; the command runs and
		// may read those flags itself. Help stays on `run help <command>`.
		$this->expectOutputString('Hello, World');
		$runner->run();
	}

	public function testHelpOptionRendersEqualsNotation(): void
	{
		$_SERVER['argv'] = ['run', 'help', 'variants'];
		$runner = new Runner(new Commands([new Fixtures\HelpVariants()]));

		ob_start();
		$runner->run();
		$raw = (string) ob_get_clean();
		$out = (string) preg_replace('/\033\[[0-9;]*m/', replacement: '', subject: $raw);

		$this->assertStringContainsString('-v, --verbose', $out);
		$this->assertStringContainsString('--prune', $out);
		$this->assertStringContainsString('-h=<host>, --host=<host>', $out);
		$this->assertStringContainsString('--release=<tag>', $out);
		$this->assertStringContainsString('-w[=<file>], --watch[=<file>]', $out);
	}
}
