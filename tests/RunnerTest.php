<?php

declare(strict_types=1);

namespace Celema\Console\Tests;

use Celema\Console\Arg;
use Celema\Console\Args;
use Celema\Console\BufferedIo;
use Celema\Console\Command;
use Celema\Console\Commands;
use Celema\Console\Io;
use Celema\Console\Runner;
use Celema\Console\Tests\Fixtures\Greet;
use Celema\Console\Tests\Fixtures\HelpVariants;
use Celema\Console\Tests\Fixtures\OptionAliases;
use ValueError;

class RunnerTest extends TestCase
{
	/** @return array{int, BufferedIo} */
	private function runProbe(object $command, string ...$args): array
	{
		$_SERVER['argv'] = ['run', 'probe', ...$args];
		$out = new BufferedIo();

		return [new Runner(new Commands([$command]), $out)->run(), $out];
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
		[$code, $errors] = $this->runVariants('--watch=src', 'file.txt');

		$this->assertSame(0, $code);
		$this->assertSame('', $errors);
	}

	public function testSeparatorSkipsOptionValidation(): void
	{
		[$code, $errors] = $this->runVariants('file.txt', '--', '--verbos');

		$this->assertSame(0, $code);
		$this->assertSame('', $errors);
	}

	public function testSeparatorStopsShortOptionNormalization(): void
	{
		$_SERVER['argv'] = ['run', 'aliases', '-v', '--', '-w=b'];
		$out = new BufferedIo();
		$code = new Runner(new Commands(new OptionAliases()), $out)->run();

		$this->assertSame(0, $code);
		$this->assertSame('[true,false,[],[]]', $out->output());
	}

	public function testRejectMissingRequiredArgument(): void
	{
		[$code, $errors] = $this->runVariants();

		$this->assertSame(1, $code);
		$this->assertStringContainsString("Missing required argument '<file>'", $errors);
	}

	public function testAcceptOmittedOptionalArgument(): void
	{
		[$code, $errors] = $this->runVariants('file.txt');

		$this->assertSame(0, $code);
		$this->assertSame('', $errors);
	}

	public function testRejectUnexpectedArgument(): void
	{
		[$code, $errors] = $this->runVariants('file.txt', 'target', 'extra');

		$this->assertSame(1, $code);
		$this->assertStringContainsString("Unexpected argument 'extra'", $errors);
	}

	public function testVariadicArgumentCollectsRemainingPositionals(): void
	{
		$command = new
			#[Command('probe', 'Variadic probe')]
			#[Arg('files', 'The files', variadic: true)]
			class {
				/** @var list<string> */
				public array $files = [];

				public function __invoke(Args $args): int
				{
					$this->files = $args->positionals();

					return 0;
				}
			};
		[$code] = $this->runProbe($command, 'a.txt', 'b.txt', 'c.txt');

		$this->assertSame(0, $code);
		$this->assertSame(['a.txt', 'b.txt', 'c.txt'], $command->files);
	}

	public function testRequiredVariadicArgumentNeedsOnePositional(): void
	{
		[$code, $out] = $this->runProbe(new
			#[Command('probe', 'Variadic probe')]
			#[Arg('files', 'The files', variadic: true)]
			class {
				public function __invoke(): int
				{
					return 0;
				}
			});

		$this->assertSame(1, $code);
		$this->assertStringContainsString("Missing required argument '<files>'", $out->errorOutput());
	}

	public function testOptionalVariadicArgumentAcceptsNoPositionals(): void
	{
		[$code] = $this->runProbe(new
			#[Command('probe', 'Variadic probe')]
			#[Arg('files', 'The files', optional: true, variadic: true)]
			class {
				public function __invoke(): int
				{
					return 0;
				}
			});

		$this->assertSame(0, $code);
	}

	public function testRejectArgumentAfterVariadic(): void
	{
		[$code, $out] = $this->runProbe(new
			#[Command('probe', 'Variadic probe')]
			#[Arg('files', 'The files', variadic: true)]
			#[Arg('extra', 'Too late')]
			class {
				public function __invoke(): int
				{
					return 0;
				}
			}, 'a.txt');

		$this->assertSame(1, $code);
		$this->assertStringContainsString(
			"Command 'probe' declares an argument after the variadic '<files>'",
			$out->errorOutput(),
		);
	}

	public function testRejectRequiredArgumentAfterOptional(): void
	{
		$_SERVER['argv'] = ['run', 'badargs', 'value'];
		$out = new BufferedIo();
		$code = new Runner(new Commands(Fixtures\BadArgOrder::class), $out)->run();

		$this->assertSame(1, $code);
		$this->assertStringContainsString(
			"Command 'badargs' declares the required argument '<second>' after an optional one",
			$out->errorOutput(),
		);
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
		$commands = new Commands([new
			#[Command('help', 'User help')]
			class {
				public function __invoke(): int
				{
					return 0;
				}
			}]);

		$this->expectException(ValueError::class);
		$this->expectExceptionMessage("Command name 'help' is reserved");

		new Runner($commands);
	}

	public function testRejectCommandsCommandRegistration(): void
	{
		$commands = new Commands([new
			#[Command('commands', 'User command list')]
			class {
				public function __invoke(): int
				{
					return 0;
				}
			}]);

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
		$commands = new Commands([new
			#[Command('boom', 'Fails with a ValueError')]
			class {
				public function __invoke(): int
				{
					throw new ValueError('Command failure', 1);
				}
			}]);
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
		[$code, $out] = $this->runProbe(new
			#[Command('probe', 'Signature probe')]
			class {
				public function __invoke(Io $io): int
				{
					$io->echo('io only');

					return 0;
				}
			});

		$this->assertSame(0, $code);
		$this->assertSame('io only', $out->output());
	}

	public function testCommandWithArgsParameterOnly(): void
	{
		$command = new
			#[Command('probe', 'Signature probe')]
			class {
				public string $seen = '';

				public function __invoke(Args $args): int
				{
					$this->seen = (string) $args->positional(0);

					return 0;
				}
			};
		[$code] = $this->runProbe($command, 'now');

		$this->assertSame(0, $code);
		$this->assertSame('now', $command->seen);
	}

	public function testCommandWithoutParameters(): void
	{
		[$code] = $this->runProbe(new
			#[Command('probe', 'Signature probe')]
			class {
				public function __invoke(): int
				{
					return 3;
				}
			});

		$this->assertSame(3, $code);
	}

	public function testCommandWithSwappedParameters(): void
	{
		[$code, $out] = $this->runProbe(new
			#[Command('probe', 'Signature probe')]
			class {
				public function __invoke(Io $io, Args $args): int
				{
					$io->echo('swapped ' . (string) $args->positional(0));

					return 0;
				}
			}, 'now');

		$this->assertSame(0, $code);
		$this->assertSame('swapped now', $out->output());
	}

	public function testRejectUntypedParameter(): void
	{
		[$code, $out] = $this->runProbe(new
			#[Command('probe', 'Signature probe')]
			class {
				/** @param mixed $args */
				public function __invoke($args, Io $io): int
				{
					return 0;
				}
			});

		$this->assertSame(1, $code);
		$this->assertStringContainsString(
			"Command 'probe' parameter \$args must be declared as Args or Io",
			$out->errorOutput(),
		);
	}

	public function testRejectForeignParameterType(): void
	{
		[$code, $out] = $this->runProbe(new
			#[Command('probe', 'Signature probe')]
			class {
				public function __invoke(string $name): int
				{
					return 0;
				}
			});

		$this->assertSame(1, $code);
		$this->assertStringContainsString(
			"Command 'probe' parameter \$name must be declared as Args or Io",
			$out->errorOutput(),
		);
	}

	public function testRejectIoSubclassParameter(): void
	{
		[$code, $out] = $this->runProbe(new
			#[Command('probe', 'Signature probe')]
			class {
				public function __invoke(BufferedIo $io): int
				{
					return 0;
				}
			});

		$this->assertSame(1, $code);
		$this->assertStringContainsString(
			"Command 'probe' parameter \$io must be declared as Args or Io",
			$out->errorOutput(),
		);
	}

	public function testRejectNullableParameter(): void
	{
		[$code, $out] = $this->runProbe(new
			#[Command('probe', 'Signature probe')]
			class {
				public function __invoke(?Args $args): int
				{
					return 0;
				}
			});

		$this->assertSame(1, $code);
		$this->assertStringContainsString(
			"Command 'probe' parameter \$args must be declared as Args or Io",
			$out->errorOutput(),
		);
	}

	public function testRejectUnionParameter(): void
	{
		[$code, $out] = $this->runProbe(new
			#[Command('probe', 'Signature probe')]
			class {
				public function __invoke(Args|Io $io): int
				{
					return 0;
				}
			});

		$this->assertSame(1, $code);
		$this->assertStringContainsString(
			"Command 'probe' parameter \$io must be declared as Args or Io",
			$out->errorOutput(),
		);
	}

	public function testRejectVariadicParameter(): void
	{
		[$code, $out] = $this->runProbe(new
			#[Command('probe', 'Signature probe')]
			class {
				public function __invoke(Io ...$io): int
				{
					return 0;
				}
			});

		$this->assertSame(1, $code);
		$this->assertStringContainsString(
			"Command 'probe' parameter \$io must be declared as Args or Io",
			$out->errorOutput(),
		);
	}

	public function testRejectDuplicateArgsParameter(): void
	{
		[$code, $out] = $this->runProbe(new
			#[Command('probe', 'Signature probe')]
			class {
				public function __invoke(Args $a, Args $b): int
				{
					return 0;
				}
			});

		$this->assertSame(1, $code);
		$this->assertStringContainsString(
			"Command 'probe' declares more than one Args parameter",
			$out->errorOutput(),
		);
	}

	public function testRejectDuplicateIoParameter(): void
	{
		[$code, $out] = $this->runProbe(new
			#[Command('probe', 'Signature probe')]
			class {
				public function __invoke(Io $a, Io $b): int
				{
					return 0;
				}
			});

		$this->assertSame(1, $code);
		$this->assertStringContainsString(
			"Command 'probe' declares more than one Io parameter",
			$out->errorOutput(),
		);
	}

	public function testRejectNonIntReturnType(): void
	{
		[$code, $out] = $this->runProbe(new
			#[Command('probe', 'Signature probe')]
			class {
				public function __invoke(): bool
				{
					return false;
				}
			});

		$this->assertSame(1, $code);
		$this->assertStringContainsString(
			"Command 'probe' must declare the return type int",
			$out->errorOutput(),
		);
	}

	public function testRejectMissingReturnType(): void
	{
		[$code, $out] = $this->runProbe(new
			#[Command('probe', 'Signature probe')]
			class {
				/** @return int */
				public function __invoke()
				{
					return 0;
				}
			});

		$this->assertSame(1, $code);
		$this->assertStringContainsString(
			"Command 'probe' must declare the return type int",
			$out->errorOutput(),
		);
	}

	public function testRejectVoidReturnType(): void
	{
		[$code, $out] = $this->runProbe(new
			#[Command('probe', 'Signature probe')]
			class {
				public function __invoke(): void {}
			});

		$this->assertSame(1, $code);
		$this->assertStringContainsString(
			"Command 'probe' must declare the return type int",
			$out->errorOutput(),
		);
	}

	public function testRejectNullableReturnType(): void
	{
		[$code, $out] = $this->runProbe(new
			#[Command('probe', 'Signature probe')]
			class {
				public function __invoke(): ?int
				{
					return 0;
				}
			});

		$this->assertSame(1, $code);
		$this->assertStringContainsString(
			"Command 'probe' must declare the return type int",
			$out->errorOutput(),
		);
	}

	public function testRejectUnionReturnType(): void
	{
		[$code, $out] = $this->runProbe(new
			#[Command('probe', 'Signature probe')]
			class {
				public function __invoke(): int|string
				{
					return 0;
				}
			});

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

	public function testRunAnonymousClassCommand(): void
	{
		$_SERVER['argv'] = ['run', 'cache:clear', 'now'];
		$commands = new Commands([new
			#[Command('cache:clear', 'Clears the cache')]
			class {
				public function __invoke(Args $args, Io $out): int
				{
					$out->echo('cleared ' . (string) $args->positional(0));

					return 0;
				}
			}]);
		$runner = new Runner($commands);

		ob_start();
		$code = $runner->run();
		$stdout = (string) ob_get_clean();

		$this->assertSame(0, $code);
		$this->assertSame('cleared now', $stdout);
	}

	public function testAnonymousClassCommandAppearsInHelp(): void
	{
		$_SERVER['argv'] = ['run'];
		$commands = new Commands([new
			#[Command('cache:clear', 'Clears the cache')]
			class {
				public function __invoke(): int
				{
					return 0;
				}
			}]);
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
