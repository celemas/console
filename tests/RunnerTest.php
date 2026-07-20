<?php

declare(strict_types=1);

namespace Celema\Console\Tests;

use Celema\Console\Args;
use Celema\Console\BufferedIo;
use Celema\Console\Commands;
use Celema\Console\Io;
use Celema\Console\Runner;
use Celema\Console\Tests\Fixtures\Greet;
use Celema\Console\Tests\Fixtures\HelpVariants;
use Celema\Console\Tests\Fixtures\OptionAliases;
use Closure;
use ValueError;

class RunnerTest extends TestCase
{
	/** @return array{int, BufferedIo} */
	private function runClosure(Closure $command, string ...$args): array
	{
		$_SERVER['argv'] = ['run', 'probe', ...$args];
		$commands = new Commands();
		$commands->add('probe', 'Signature probe', $command);
		$out = new BufferedIo();

		return [new Runner($commands, $out)->run(), $out];
	}

	private function runVariants(string ...$args): array
	{
		$_SERVER['argv'] = ['run', 'help:variants', ...$args];
		$out = new BufferedIo();
		$runner = new Runner(new Commands([new HelpVariants()]), $out);

		return [$runner->run(), $out->errorOutput()];
	}

	public function testRejectUnknownOptionWithSuggestion(): void
	{
		[$code, $errors] = $this->runVariants('--verbos');

		$this->assertSame(1, $code);
		$this->assertStringContainsString(
			"Unknown option '--verbos'. Did you mean '--verbose'?",
			$errors,
		);
	}

	public function testRejectUnknownOptionWithoutSuggestion(): void
	{
		[$code, $errors] = $this->runVariants('--completely-different');

		$this->assertSame(1, $code);
		$this->assertStringContainsString("Unknown option '--completely-different'", $errors);
		$this->assertStringNotContainsString('Did you mean', $errors);
	}

	public function testUndeclaredHelpFlagHintsAtTheHelpCommand(): void
	{
		[$code, $errors] = $this->runVariants('--help');

		$this->assertSame(1, $code);
		$this->assertStringContainsString(
			"Unknown option '--help'. Use 'php run help help:variants' to show the command's help",
			$errors,
		);
	}

	public function testRejectValueOnBooleanOption(): void
	{
		[$code, $errors] = $this->runVariants('--prune=now');

		$this->assertSame(1, $code);
		$this->assertStringContainsString("Option '--prune' does not accept a value", $errors);
	}

	public function testRejectMissingRequiredOptionValue(): void
	{
		[$code, $errors] = $this->runVariants('--host');

		$this->assertSame(1, $code);
		$this->assertStringContainsString("Option '--host' requires a value: --host=<host>", $errors);
	}

	public function testAcceptDeclaredOptions(): void
	{
		[$code, $errors] = $this->runVariants('-v', '--host=localhost', '--watch', 'file.txt');

		$this->assertSame(0, $code);
		$this->assertSame('', $errors);
	}

	public function testNormalizesShortOptionsToLongNames(): void
	{
		$_SERVER['argv'] = ['run', 'aliases', '-v', '--watch=a', '-w=b', '--watch=c'];
		$out = new BufferedIo();
		$code = new Runner(new Commands(new OptionAliases()), $out)->run();

		$this->assertSame(0, $code);
		$this->assertSame('[true,false,["a","b","c"],[]]', $out->output());
	}

	public function testOptionalValueAcceptsAValue(): void
	{
		[$code, $errors] = $this->runVariants('--watch=src');

		$this->assertSame(0, $code);
		$this->assertSame('', $errors);
	}

	public function testCommandWithoutDeclaredOptionsAcceptsAnything(): void
	{
		$_SERVER['argv'] = ['run', 'greet', '--greeting=Hi', '--whatever'];
		$out = new BufferedIo();
		$runner = new Runner(new Commands([new Greet()]), $out);

		$this->assertSame(0, $runner->run());
		$this->assertSame('Hi, World', $out->output());
	}

	public function testRejectHelpCommandRegistration(): void
	{
		$commands = new Commands();
		$commands->add('help', 'User help', static fn(Args $args, Io $io): int => 0);

		$this->expectException(ValueError::class);
		$this->expectExceptionMessage("Command name 'help' is reserved");

		new Runner($commands);
	}

	public function testRejectCommandsCommandRegistration(): void
	{
		$commands = new Commands();
		$commands->add('commands', 'User command list', static fn(Args $args, Io $io): int => 0);

		$this->expectException(ValueError::class);
		$this->expectExceptionMessage("Command name 'commands' is reserved");

		new Runner($commands);
	}

	public function testRejectDuplicateCommandRegistration(): void
	{
		$this->expectException(ValueError::class);
		$this->expectExceptionMessage("Duplicate command 'plain'");

		new Runner(new Commands([new Fixtures\Plain(), new Fixtures\Plain()]));
	}

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

	public function testCommandValueErrorIsNotTreatedAsAmbiguous(): void
	{
		$_SERVER['argv'] = ['run', 'boom'];
		$commands = new Commands();
		$commands->add(
			'boom',
			'Fails with a ValueError',
			static function (Args $args, Io $io): int {
				throw new ValueError('Command failure', 1);
			},
		);
		$out = new BufferedIo();
		$code = new Runner($commands, $out)->run();

		$this->assertSame(1, $code);
		$this->assertStringContainsString('Command failure', $out->errorOutput());
		$this->assertStringNotContainsString('Ambiguous command', $out->errorOutput());
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

	public function testCommandWithIoParameterOnly(): void
	{
		[$code, $out] = $this->runClosure(static function (Io $io): int {
			$io->echo('io only');

			return 0;
		});

		$this->assertSame(0, $code);
		$this->assertSame('io only', $out->output());
	}

	public function testCommandWithArgsParameterOnly(): void
	{
		$seen = '';
		[$code] = $this->runClosure(static function (Args $args) use (&$seen): int {
			$seen = (string) $args->positional(0);

			return 0;
		}, 'now');

		$this->assertSame(0, $code);
		$this->assertSame('now', $seen);
	}

	public function testCommandWithoutParameters(): void
	{
		[$code] = $this->runClosure(static fn(): int => 3);

		$this->assertSame(3, $code);
	}

	public function testCommandWithSwappedParameters(): void
	{
		[$code, $out] = $this->runClosure(static function (Io $io, Args $args): int {
			$io->echo('swapped ' . (string) $args->positional(0));

			return 0;
		}, 'now');

		$this->assertSame(0, $code);
		$this->assertSame('swapped now', $out->output());
	}

	public function testRejectUntypedParameter(): void
	{
		[$code, $out] = $this->runClosure(static fn($args, Io $io): int => 0);

		$this->assertSame(1, $code);
		$this->assertStringContainsString(
			"Command 'probe' parameter \$args must be declared as Args or Io",
			$out->errorOutput(),
		);
	}

	public function testRejectForeignParameterType(): void
	{
		[$code, $out] = $this->runClosure(static fn(string $name): int => 0);

		$this->assertSame(1, $code);
		$this->assertStringContainsString(
			"Command 'probe' parameter \$name must be declared as Args or Io",
			$out->errorOutput(),
		);
	}

	public function testRejectIoSubclassParameter(): void
	{
		[$code, $out] = $this->runClosure(static fn(BufferedIo $io): int => 0);

		$this->assertSame(1, $code);
		$this->assertStringContainsString(
			"Command 'probe' parameter \$io must be declared as Args or Io",
			$out->errorOutput(),
		);
	}

	public function testRejectNullableParameter(): void
	{
		[$code, $out] = $this->runClosure(static fn(?Args $args): int => 0);

		$this->assertSame(1, $code);
		$this->assertStringContainsString(
			"Command 'probe' parameter \$args must be declared as Args or Io",
			$out->errorOutput(),
		);
	}

	public function testRejectUnionParameter(): void
	{
		[$code, $out] = $this->runClosure(static fn(Args|Io $io): int => 0);

		$this->assertSame(1, $code);
		$this->assertStringContainsString(
			"Command 'probe' parameter \$io must be declared as Args or Io",
			$out->errorOutput(),
		);
	}

	public function testRejectVariadicParameter(): void
	{
		[$code, $out] = $this->runClosure(static fn(Io ...$io): int => 0);

		$this->assertSame(1, $code);
		$this->assertStringContainsString(
			"Command 'probe' parameter \$io must be declared as Args or Io",
			$out->errorOutput(),
		);
	}

	public function testRejectDuplicateArgsParameter(): void
	{
		[$code, $out] = $this->runClosure(static fn(Args $a, Args $b): int => 0);

		$this->assertSame(1, $code);
		$this->assertStringContainsString(
			"Command 'probe' declares more than one Args parameter",
			$out->errorOutput(),
		);
	}

	public function testRejectDuplicateIoParameter(): void
	{
		[$code, $out] = $this->runClosure(static fn(Io $a, Io $b): int => 0);

		$this->assertSame(1, $code);
		$this->assertStringContainsString(
			"Command 'probe' declares more than one Io parameter",
			$out->errorOutput(),
		);
	}

	public function testRejectNonIntReturnType(): void
	{
		[$code, $out] = $this->runClosure(static fn(Args $args, Io $io): bool => false);

		$this->assertSame(1, $code);
		$this->assertStringContainsString(
			"Command 'probe' must declare the return type int",
			$out->errorOutput(),
		);
	}

	public function testRejectMissingReturnType(): void
	{
		[$code, $out] = $this->runClosure(static fn(Args $args, Io $io) => 0);

		$this->assertSame(1, $code);
		$this->assertStringContainsString(
			"Command 'probe' must declare the return type int",
			$out->errorOutput(),
		);
	}

	public function testRejectVoidReturnType(): void
	{
		[$code, $out] = $this->runClosure(static function (Args $args, Io $io): void {});

		$this->assertSame(1, $code);
		$this->assertStringContainsString(
			"Command 'probe' must declare the return type int",
			$out->errorOutput(),
		);
	}

	public function testRejectNullableReturnType(): void
	{
		[$code, $out] = $this->runClosure(static fn(Args $args, Io $io): ?int => 0);

		$this->assertSame(1, $code);
		$this->assertStringContainsString(
			"Command 'probe' must declare the return type int",
			$out->errorOutput(),
		);
	}

	public function testRejectUnionReturnType(): void
	{
		[$code, $out] = $this->runClosure(static fn(Args $args, Io $io): int|string => 0);

		$this->assertSame(1, $code);
		$this->assertStringContainsString(
			"Command 'probe' must declare the return type int",
			$out->errorOutput(),
		);
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

	public function testRunClassStringCommand(): void
	{
		$_SERVER['argv'] = ['run', 'plain'];
		$runner = new Runner(new Commands(Fixtures\Plain::class));

		$this->expectOutputString('Plain');
		$runner->run();
	}

	public function testRunNamedClosureCommand(): void
	{
		$_SERVER['argv'] = ['run', 'cache:clear', 'now'];
		$commands = new Commands();
		$commands->add(
			'cache:clear',
			'Clears the cache',
			static function (Args $args, Io $out): int {
				$out->echo('cleared ' . (string) $args->positional(0));

				return 0;
			},
		);
		$runner = new Runner($commands);

		ob_start();
		$code = $runner->run();
		$stdout = (string) ob_get_clean();

		$this->assertSame(0, $code);
		$this->assertSame('cleared now', $stdout);
	}

	public function testClosureCommandAppearsInHelp(): void
	{
		$_SERVER['argv'] = ['run'];
		$commands = new Commands();
		$commands->add('cache:clear', 'Clears the cache', static fn(Args $args, Io $out): int => 0);
		$runner = new Runner($commands);

		$this->expectOutputRegex('/Cache.*cache:.*clear.*Clears the cache/s');
		$runner->run();
	}

	public function testFactoryRunsOnlyForTheInvokedCommand(): void
	{
		$_SERVER['argv'] = ['run', 'help'];
		$called = false;
		$factory = static function () use (&$called): Fixtures\Greet {
			$called = true;

			return new Fixtures\Greet();
		};
		$commands = new Commands([Fixtures\Greet::class => $factory]);

		ob_start();
		new Runner($commands)->run();
		ob_get_clean();

		$this->assertFalse($called);

		$_SERVER['argv'] = ['run', 'greet'];

		ob_start();
		new Runner($commands)->run();
		ob_get_clean();

		$this->assertTrue($called);
	}

	public function testUninvokableCommandFails(): void
	{
		$_SERVER['argv'] = ['run', 'broken'];
		$runner = new Runner(
			new Commands(Fixtures\Uninvokable::class),
			output: 'php://output',
			errorOutput: 'php://output',
		);

		ob_start();
		$code = $runner->run();
		$stdout = (string) ob_get_clean();

		$this->assertSame(1, $code);
		$this->assertStringContainsString("Command 'broken' is not callable", $stdout);
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
