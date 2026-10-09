<?php

declare(strict_types=1);

namespace Celema\Console\Tests;

use Celema\Console\Arg;
use Celema\Console\Args;
use Celema\Console\Buffer;
use Celema\Console\Command;
use Celema\Console\Exception\InvalidUsage;
use Celema\Console\Io;
use Celema\Console\Opt;
use Celema\Console\Runner;
use Celema\Console\Stdio;
use Celema\Console\Tests\Fixtures\Greet;
use Celema\Console\Tests\Fixtures\HelpVariants;
use Celema\Console\Tests\Fixtures\OptionAliases;
use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use stdClass;
use ValueError;

class RunnerTest extends TestCase
{
	/** @return array{int, Buffer} */
	private function runProbe(object $command, string ...$args): array
	{
		$out = new Buffer();

		return [new Runner([$command], new Io($out))->run(['run', 'probe', ...$args]), $out];
	}

	private function runVariants(string ...$args): array
	{
		$out = new Buffer();
		$runner = new Runner([new HelpVariants()], new Io($out));

		return [$runner->run(['run', 'help:variants', ...$args]), $out->errorOutput()];
	}

	public function testRejectUnknownOptionWithSuggestion(): void
	{
		[$code, $errors] = $this->runVariants('--verbos');

		$this->assertSame(2, $code);
		$this->assertStringContainsString(
			"Unknown option '--verbos'. Did you mean '--verbose'?",
			$errors,
		);
	}

	public function testSuggestionAcceptsADistanceOfThree(): void
	{
		[, $errors] = $this->runVariants('--verb');

		$this->assertStringContainsString("Unknown option '--verb'. Did you mean '--verbose'?", $errors);
	}

	public function testSuggestionPrefersTheFirstDeclaredOptionOnTies(): void
	{
		[, $errors] = $this->runVariants('-x');

		$this->assertStringContainsString("Unknown option '-x'. Did you mean '-v'?", $errors);
	}

	public function testRejectUnknownOptionWithoutSuggestion(): void
	{
		[$code, $errors] = $this->runVariants('--completely-different');

		$this->assertSame(2, $code);
		$this->assertStringContainsString("Unknown option '--completely-different'", $errors);
		$this->assertStringNotContainsString('Did you mean', $errors);
	}

	public function testUndeclaredHelpFlagHintsAtTheHelpCommand(): void
	{
		[$code, $errors] = $this->runVariants('--help');

		$this->assertSame(2, $code);
		$this->assertStringContainsString(
			"Unknown option '--help'. Use 'php run help help:variants' to show the command's help",
			$errors,
		);
	}

	public function testHelpFlagHintUsesTheScriptName(): void
	{
		$out = new Buffer();
		new Runner([new HelpVariants()], new Io($out))->run(['bin/console', 'help:variants', '--help']);

		$this->assertStringContainsString(
			"Unknown option '--help'. Use 'php bin/console help help:variants' to show the command's help",
			$out->errorOutput(),
		);
	}

	public function testRejectValueOnBooleanOption(): void
	{
		[$code, $errors] = $this->runVariants('--prune=now');

		$this->assertSame(2, $code);
		$this->assertStringContainsString("Option '--prune' does not accept a value", $errors);
	}

	public function testRejectMissingRequiredOptionValue(): void
	{
		[$code, $errors] = $this->runVariants('--host');

		$this->assertSame(2, $code);
		$this->assertStringContainsString("Option '--host' requires a value: --host=<host>", $errors);
	}

	public function testRejectBareOccurrenceOfRepeatedValueOption(): void
	{
		[$code, $errors] = $this->runVariants('--host', '--host=localhost', 'file.txt');

		$this->assertSame(2, $code);
		$this->assertStringContainsString("Option '--host' requires a value: --host=<host>", $errors);

		[$code, $errors] = $this->runVariants('--host=localhost', '--host', 'file.txt');

		$this->assertSame(2, $code);
		$this->assertStringContainsString("Option '--host' requires a value: --host=<host>", $errors);
	}

	public function testRejectBareAndValuedOccurrenceOfSingleValueOption(): void
	{
		[$code, $errors] = $this->runVariants('--watch', '--watch=src', 'file.txt');

		$this->assertSame(2, $code);
		$this->assertStringContainsString("Option '--watch' accepts only one value", $errors);
	}

	public function testAcceptDeclaredOptions(): void
	{
		[$code, $errors] = $this->runVariants('-v', '--host=localhost', '--watch', 'file.txt');

		$this->assertSame(0, $code);
		$this->assertSame('', $errors);
	}

	public function testNormalizesShortOptionsToLongNames(): void
	{
		$out = new Buffer();
		$code = new Runner(new OptionAliases(), new Io($out))->run([
			'run',
			'aliases',
			'-v',
			'--watch=a',
			'-w=b',
			'--watch=c',
		]);

		$this->assertSame(0, $code);
		$this->assertSame('[true,false,["a","b","c"],[],true,["a","b","c"]]', $out->output());
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
		$out = new Buffer();
		$code = new Runner(new OptionAliases(), new Io($out))->run(['run', 'aliases', '-v', '--', '-w=b']);

		$this->assertSame(0, $code);
		$this->assertSame('[true,false,[],[],true,[]]', $out->output());
	}

	public function testSeparatorPassesLaterTokensUnchanged(): void
	{
		[$code, $out] = $this->runProbe(
			new
				#[Command('probe')]
				class {
					/** @param list<string> $rest */
					public function __invoke(
						Io $io,
						#[Opt('Verbose output', short: '-v')]
						bool $verbose = false,
						#[Arg('Remaining tokens')]
						array $rest = [],
					): int {
						$io->echo((string) json_encode([$verbose, $rest]));

						return 0;
					}
				},
			'-v',
			'--',
			'-v',
			'x',
		);

		$this->assertSame(0, $code);
		$this->assertSame('[true,["-v","x"]]', $out->output());
	}

	public function testRejectMissingRequiredArgument(): void
	{
		[$code, $errors] = $this->runVariants();

		$this->assertSame(2, $code);
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

		$this->assertSame(2, $code);
		$this->assertStringContainsString("Unexpected argument 'extra'", $errors);
	}

	public function testVariadicArgumentCollectsRemainingPositionals(): void
	{
		$command = new
			#[Command('probe', 'Variadic probe')]
			class {
				/** @var list<string> */
				public array $files = [];

				/** @param list<string> $files */
				public function __invoke(#[Arg('The files')] array $files): int
				{
					$this->files = $files;

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
			class {
				/** @param list<string> $files */
				public function __invoke(#[Arg('The files')] array $files): int
				{
					return 0;
				}
			});

		$this->assertSame(2, $code);
		$this->assertStringContainsString("Missing required argument '<files>'", $out->errorOutput());
	}

	public function testOptionalVariadicArgumentAcceptsNoPositionals(): void
	{
		[$code] = $this->runProbe(new
			#[Command('probe', 'Variadic probe')]
			class {
				/** @param list<string> $files */
				public function __invoke(#[Arg('The files')] array $files = []): int
				{
					return 0;
				}
			});

		$this->assertSame(0, $code);
	}

	public function testRejectArgumentAfterVariadic(): void
	{
		[$code, $out] = $this->runProbe(
			new
				#[Command('probe', 'Variadic probe')]
				class {
					/** @param list<string> $files */
					public function __invoke(#[Arg('The files')] array $files, #[Arg('Too late')] string $extra): int
					{
						return 0;
					}
				},
			'a.txt',
		);

		$this->assertSame(1, $code);
		$this->assertStringContainsString(
			"Command 'probe' declares an argument after the variadic '<files>'",
			$out->errorOutput(),
		);
	}

	public function testRejectDuplicateOptionName(): void
	{
		[$code, $out] = $this->runProbe(new
			#[Command('probe', 'Duplicate option')]
			class {
				// Parameter names are case-sensitive, command-line names are not.
				public function __invoke(
					#[Opt('First')]
					bool $force = false,
					#[Opt('Second')]
					bool $Force = false,
				): int {
					return 0;
				}
			});

		$this->assertSame(1, $code);
		$this->assertStringContainsString(
			"Command 'probe' declares the option name '--force' twice",
			$out->errorOutput(),
		);
	}

	public function testRejectDuplicateShortAlias(): void
	{
		[$code, $out] = $this->runProbe(new
			#[Command('probe', 'Duplicate alias')]
			class {
				public function __invoke(
					#[Opt('First', short: '-x')]
					bool $alpha = false,
					#[Opt('Second', short: '-x')]
					bool $beta = false,
				): int {
					return 0;
				}
			});

		$this->assertSame(1, $code);
		$this->assertStringContainsString(
			"Command 'probe' declares the option name '-x' twice",
			$out->errorOutput(),
		);
	}

	public function testRejectUndeclaredOption(): void
	{
		$out = new Buffer();

		$this->assertSame(
			2,
			new Runner([new Fixtures\Plain()], new Io($out))->run(['run', 'plain', '--whatever']),
		);
		$this->assertStringContainsString("Unknown option '--whatever'", $out->errorOutput());
	}

	public function testRejectUndeclaredPositional(): void
	{
		$out = new Buffer();

		$this->assertSame(2, new Runner([new Fixtures\Plain()], new Io($out))->run(['run', 'plain', 'extra']));
		$this->assertStringContainsString("Unexpected argument 'extra'", $out->errorOutput());
	}

	public function testRejectHelpCommandRegistration(): void
	{
		$commands = [new
			#[Command('help', 'User help')]
			class {
				public function __invoke(): int
				{
					return 0;
				}
			}];

		$this->expectException(ValueError::class);
		$this->expectExceptionMessage("Command name 'help' is reserved");

		new Runner($commands);
	}

	public function testRejectCommandsCommandRegistration(): void
	{
		$commands = [new
			#[Command('commands', 'User command list')]
			class {
				public function __invoke(): int
				{
					return 0;
				}
			}];

		$this->expectException(ValueError::class);
		$this->expectExceptionMessage("Command name 'commands' is reserved");

		new Runner($commands);
	}

	public function testRejectDuplicateCommandRegistration(): void
	{
		$this->expectException(ValueError::class);
		$this->expectExceptionMessage("Duplicate command 'plain'");

		new Runner([new Fixtures\Plain(), new Fixtures\Plain()]);
	}

	public function testRunnerWithoutCommandsListsOnlyTheBuiltins(): void
	{
		$out = new Buffer();

		$this->assertSame(0, new Runner(io: new Io($out))->run(['bin/console']));
		$this->assertStringEndsWith(
			<<<'TEXT'
				Available commands:

				General
				  commands  Lists all available commands
				  help      Displays this overview

				TEXT,
			$out->output(),
		);
	}

	public function testAddRegistersEveryShape(): void
	{
		$out = new Buffer();
		$runner = new Runner([new Fixtures\Plain()], new Io($out));

		$this->assertSame($runner, $runner->add(Fixtures\Greet::class));
		$runner->add([
			Fixtures\BarStuff::class => static fn(): Fixtures\BarStuff => new Fixtures\BarStuff(),
			new Fixtures\FooStuff(),
		]);

		$runner->run(['run', 'commands']);

		$this->assertSame(0, $runner->run(['run', 'greet', 'Ada']));
		$this->assertSame("bar:stuff\nfoo:stuff\ngreet\nplain\nHello, Ada", $out->output());
	}

	public function testHelpSortsCommandsAddedOutOfOrder(): void
	{
		$out = new Buffer();
		new Runner([new Fixtures\FooStuff(), new Fixtures\Plain()], new Io($out))
			->add([new Fixtures\BarStuff(), new Fixtures\Greet()])
			->add([new Fixtures\FooDrivel(), new Fixtures\Erring()])
			->run(['bin/console']);

		$this->assertStringEndsWith(
			<<<'TEXT'
				General
				  commands    Lists all available commands
				  help        Displays this overview
				  greet       Greets a name
				  plain       An ungrouped command

				Bar
				  bar:stuff   Prints Bar's stuff to stdout

				Errors
				  err:err     Throws an error

				Foo
				  foo:drivel  Prints Foo's drivel to stdout
				  foo:stuff   Prints Foo's stuff to stdout

				TEXT,
			$out->output(),
		);
	}

	public function testAddRejectsADuplicateOfAnEarlierRegistration(): void
	{
		$runner = new Runner([new Fixtures\Plain()]);

		$this->expectException(ValueError::class);
		$this->expectExceptionMessage("Duplicate command 'plain'");

		$runner->add(Fixtures\Plain::class);
	}

	/** @return iterable<string, array{array, string}> */
	public static function rejectedRegistrationProvider(): iterable
	{
		yield 'duplicate' => [[Fixtures\BarStuff::class, Fixtures\Plain::class], "Duplicate command 'plain'"];
		yield 'reserved' => [
			[
				Fixtures\BarStuff::class,
				new
					#[Command('help', 'User help')]
					class {
						public function __invoke(): int
						{
							return 0;
						}
					},
			],
			"Command name 'help' is reserved",
		];
		yield 'unknown class' => [
			[Fixtures\BarStuff::class, 'Missing\\Command'],
			"Unknown command class 'Missing\\Command'",
		];
		yield 'unknown factory class' => [
			[Fixtures\BarStuff::class, 'Missing\\Command' => static fn(): Fixtures\Plain => new Fixtures\Plain()],
			"Unknown command class 'Missing\\Command'",
		];
		yield 'non-closure factory' => [
			[Fixtures\BarStuff::class, Fixtures\Greet::class => new Fixtures\Greet()],
			"Factory for command class 'Celema\\Console\\Tests\\Fixtures\\Greet' must be a closure",
		];
		yield 'closure' => [
			[Fixtures\BarStuff::class, static fn(): int => 0],
			'Closure commands are not supported; use an anonymous class with a #[Command] attribute',
		];
		yield 'invalid item' => [[Fixtures\BarStuff::class, 42], 'Invalid command registration'];
		yield 'nested array' => [[Fixtures\BarStuff::class, [new Fixtures\Greet()]], 'Invalid command registration'];
		yield 'no attribute' => [
			[Fixtures\BarStuff::class, new stdClass()],
			"Command class 'stdClass' has no #[Command] attribute",
		];
	}

	#[DataProvider('rejectedRegistrationProvider')]
	public function testRejectedAddRegistersNone(array $commands, string $message): void
	{
		$out = new Buffer();
		$runner = new Runner([new Fixtures\Plain()], new Io($out));

		try {
			$runner->add($commands);
			$this->fail('The registration was accepted');
		} catch (ValueError $e) {
			$this->assertSame($message, $e->getMessage());
		}

		$runner->showCommands();
		$this->assertSame("plain\n", $out->output());
	}

	public function testRunnerResolverConstructsItsClassStringsOnInvocation(): void
	{
		$out = new Buffer();
		$io = new Io($out);
		$resolved = [];
		$runner = new Runner(
			Fixtures\InjectedGreet::class,
			$io,
			resolve: static function (string $class) use (&$resolved, $io): object {
				$resolved[] = $class;

				return $class === Fixtures\InjectedGreet::class ? new $class($io) : new $class();
			},
		);
		$runner->add(Fixtures\Plain::class);

		foreach ([['run'], ['run', 'commands'], ['run', 'help', 'plain']] as $argv) {
			$this->assertSame(0, $runner->run($argv));
		}

		$this->assertSame([], $resolved);

		$this->assertSame(0, $runner->run(['run', 'greet:injected', 'Ada']));
		$this->assertSame(0, $runner->run(['run', 'plain']));
		$this->assertSame(0, $runner->run(['run', 'plain']));

		$this->assertSame([Fixtures\InjectedGreet::class, Fixtures\Plain::class], $resolved);
		$this->assertStringContainsString('Hello, Ada', $out->output());
	}

	public function testResolverAcceptsMethodCallable(): void
	{
		$resolver = new class {
			public function resolve(string $class): object
			{
				return new $class();
			}
		};
		$out = new Buffer();

		$this->assertSame(
			0,
			new Runner(Fixtures\Plain::class, new Io($out), resolve: [$resolver, 'resolve'])->run(['run', 'plain']),
		);
		$this->assertSame('Plain', $out->output());
	}

	public function testInstancesAndExplicitFactoriesBypassResolver(): void
	{
		$out = new Buffer();
		$runner = new Runner(
			[new Fixtures\Greet(), Fixtures\Plain::class => static fn(): Fixtures\Plain => new Fixtures\Plain()],
			new Io($out),
			resolve: fn(string $class): object => $this->fail("Unexpected resolution of {$class}"),
		);

		$this->assertSame(0, $runner->run(['run', 'greet', 'Ada']));
		$this->assertSame(0, $runner->run(['run', 'plain']));
		$this->assertSame('Hello, AdaPlain', $out->output());
	}

	/** @return iterable<string, array{array, ?callable}> */
	public static function wrongInstanceProvider(): iterable
	{
		yield 'factory returning null' => [[Fixtures\Greet::class => static fn(): mixed => null], null];
		yield 'factory returning another class' => [
			[Fixtures\Greet::class => static fn(): Fixtures\Plain => new Fixtures\Plain()],
			null,
		];
		yield 'resolver returning null' => [[Fixtures\Greet::class], static fn(string $class): mixed => null];
		yield 'resolver returning another class' => [
			[Fixtures\Greet::class],
			static fn(string $class): Fixtures\Plain => new Fixtures\Plain(),
		];
	}

	#[DataProvider('wrongInstanceProvider')]
	public function testWrongCommandInstanceFails(array $commands, ?callable $resolve): void
	{
		$out = new Buffer();

		$this->assertSame(1, new Runner($commands, new Io($out), resolve: $resolve)->run(['run', 'greet']));
		$this->assertStringContainsString(
			"Factory for command 'greet' must return a " . Fixtures\Greet::class,
			$out->errorOutput(),
		);
	}

	public function testHelpOverviewLayout(): void
	{
		$out = new Buffer();
		$code = new Runner($this->getCommands(), new Io($out))->add(new Fixtures\Plain())->run(['bin/console']);

		$this->assertSame(0, $code);
		$this->assertSame(
			<<<'TEXT'
				Usage:
				  php bin/console [prefix:]command [arguments]

				Prefixes are optional if the command is unambiguous.

				Available commands:

				General
				  commands    Lists all available commands
				  help        Displays this overview
				  plain       An ungrouped command

				Bar
				  bar:stuff   Prints Bar's stuff to stdout

				Errors
				  err:err     Throws an error

				Foo
				  foo:drivel  Prints Foo's drivel to stdout
				  foo:stuff   Prints Foo's stuff to stdout

				TEXT,
			$out->output(),
		);
	}

	public function testHelpOverviewListsNumericNamesAndPrefixes(): void
	{
		$out = new Buffer();
		$commands = [
			new
				#[Command('7', 'Numbered')]
				class {
					public function __invoke(): int
					{
						return 0;
					}
				},
			new
				#[Command('0:task', 'Numbered')]
				class {
					public function __invoke(): int
					{
						return 0;
					}
				},
			new
				#[Command('2026:import', 'Numbered')]
				class {
					public function __invoke(): int
					{
						return 0;
					}
				},
			new
				#[Command('task:2026', 'Numbered')]
				class {
					public function __invoke(): int
					{
						return 0;
					}
				},
		];
		$code = new Runner($commands, new Io($out))->run(['bin/console']);

		$this->assertSame(0, $code);
		$this->assertStringEndsWith(
			<<<'TEXT'
				General
				  commands     Lists all available commands
				  help         Displays this overview
				  7            Numbered

				0
				  0:task       Numbered

				2026
				  2026:import  Numbered

				Task
				  task:2026    Numbered

				TEXT,
			$out->output(),
		);
	}

	public function testShowHelpAndShowCommandsArePublic(): void
	{
		$out = new Buffer();
		$runner = new Runner($this->getCommands(), new Io($out));

		$this->assertSame(0, $runner->showCommands());
		$this->assertSame("bar:stuff\ndrivel\nerr\nerr:err\nfoo:drivel\nfoo:stuff\n", $out->output());
		$this->assertSame(0, $runner->showHelp());
		$this->assertStringContainsString('Available commands:', $out->output());
	}

	public function testCommandNamesAreCaseInsensitive(): void
	{
		$out = new Buffer();
		new Runner($this->getCommands(), new Io($out))->run(['run', 'DRIVEL']);

		$this->assertSame("Foo's drivel", $out->output());

		$out = new Buffer();
		$code = new Runner($this->getCommands(), new Io($out))->run(['run', 'help', 'FOO:STUFF']);

		$this->assertSame(0, $code);
		$this->assertStringContainsString('php run foo:stuff', $out->output());
	}

	public function testShowHelpWhenCalledWithoutCommand(): void
	{
		$runner = $this->getRunner();

		$this->expectOutputRegex("/available commands.*bar.*prints foo's stuff/si");
		$runner->run(['run']);
	}

	public function testShowHelpWhenCalledWithHelpCommand(): void
	{
		$runner = $this->getRunner();

		$this->expectOutputRegex("/available commands.*prints bar's stuff.*foo/si");
		$runner->run(['run', 'help']);
	}

	public function testListCommands(): void
	{
		$runner = $this->getRunner();

		$this->expectOutputString("bar:stuff\ndrivel\nerr\nerr:err\nfoo:drivel\nfoo:stuff\n");
		$runner->run(['run', 'commands']);
	}

	public function testShowCommandSpecificHelp(): void
	{
		$runner = $this->getRunner();

		$this->expectOutputRegex('/php run foo:stuff.*Options:.*Lorem ipsum/s');
		$runner->run(['run', 'help', 'foo:stuff']);
	}

	public function testCommandSpecificHelpDefault(): void
	{
		$runner = $this->getRunner();

		$this->expectOutputRegex('/php run bar:stuff/');
		$runner->run(['run', 'help', 'bar:stuff']);
	}

	public function testShowHelpInOrder(): void
	{
		$runner = $this->getRunner();

		$this->expectOutputRegex(
			'/Available.*Bar.*bar:.*stuff.*Errors.*err:.*err.*Foo.*foo:.*drivel.*stuff/s',
		);
		$runner->run(['run']);
	}

	public function testRunSimpleCommand(): void
	{
		$runner = $this->getRunner();

		$this->expectOutputString("Foo's drivel");
		$runner->run(['run', 'drivel']);
	}

	#[BackupGlobals(true)]
	public function testDefaultsToTheServerArgumentVector(): void
	{
		$_SERVER['argv'] = ['bin/console', 'help', 'help:variants'];
		$out = new Buffer();
		$runner = new Runner([new HelpVariants()], new Io($out));

		$this->assertSame(0, $runner->run());
		$this->assertStringContainsString('php bin/console help:variants <file>', $out->output());
		$this->assertSame(0, $runner->showHelp());
		$this->assertStringContainsString('php bin/console [prefix:]command', $out->output());
	}

	public static function explicitScriptNameProvider(): iterable
	{
		yield 'overview' => [['bin/tool'], 'php bin/tool [prefix:]command'];
		yield 'help command' => [['bin/tool', 'help'], 'php bin/tool [prefix:]command'];
		yield 'command help' => [['bin/tool', 'help', 'help:variants'], 'php bin/tool help:variants <file>'];
	}

	/** @param list<string> $argv */
	#[DataProvider('explicitScriptNameProvider')]
	public function testExplicitArgumentVectorNamesTheScript(array $argv, string $expected): void
	{
		$out = new Buffer();
		new Runner([new HelpVariants()], new Io($out))->run($argv);

		$this->assertStringContainsString($expected, $out->output());
	}

	public function testRunAmbiguousCommand(): void
	{
		$runner = $this->getRunner();

		$this->expectOutputRegex('/Ambiguous.*bar.*:stuff.*foo.*:stuff/s');
		$this->assertSame(2, $runner->run(['run', 'stuff']));
	}

	public function testUnprefixedCommandWinsOverPrefixedNamesake(): void
	{
		$commands = [
			new
				#[Command('deploy', 'Unprefixed')]
				class {
					public function __invoke(Io $io): int
					{
						$io->echo('plain deploy');

						return 0;
					}
				},
			new
				#[Command('ops:deploy', 'Prefixed')]
				class {
					public function __invoke(Io $io): int
					{
						$io->echo('ops deploy');

						return 0;
					}
				},
		];

		$out = new Buffer();
		$this->assertSame(0, new Runner($commands, new Io($out))->run(['run', 'deploy']));
		$this->assertSame('plain deploy', $out->output());

		$out = new Buffer();
		$this->assertSame(0, new Runner($commands, new Io($out))->run(['run', 'ops:deploy']));
		$this->assertSame('ops deploy', $out->output());

		// Both stay invocable, so both appear in the listing.
		$out = new Buffer();
		new Runner($commands, new Io($out))->run(['run', 'commands']);
		$this->assertSame("deploy\nops:deploy\n", $out->output());
	}

	public function testRunGroupNameCommand(): void
	{
		$runner = $this->getRunner();

		$this->expectOutputString("Bar's stuff");
		$runner->run(['run', 'bar:stuff']);
	}

	public function testHelpForUnknownCommandNamesTheTarget(): void
	{
		$out = new Buffer();
		$code = new Runner([new HelpVariants()], new Io($out))->run(['run', 'help', 'missing']);

		$this->assertSame(2, $code);
		$this->assertStringContainsString("Error while running command 'missing'", $out->errorOutput());
	}

	public function testRunUnknownCommand(): void
	{
		$runner = $this->getRunner();

		$this->expectOutputRegex('/Command not found/');
		$runner->run(['run', 'unknown']);
	}

	public function testRunUnknownGroupCommand(): void
	{
		$runner = $this->getRunner();

		$this->expectOutputRegex('/Command not found/');
		$runner->run(['run', 'foo:unknown']);
	}

	public function testRunCommandWithExtraColonsNotFound(): void
	{
		$runner = $this->getRunner();

		$this->expectOutputRegex('/Command not found/');
		$runner->run(['run', 'foo:stuff:extra']);
	}

	public function testUngroupedCommandsShareSingleGeneralHeader(): void
	{
		$runner = new Runner(
			[new Fixtures\Plain(), new Fixtures\BarStuff()],
			new Io(new Stdio('php://output')),
		);

		ob_start();
		$runner->run(['run']);
		$raw = (string) ob_get_clean();
		$out = (string) preg_replace('/\033\[[0-9;]*m/', replacement: '', subject: $raw);

		$this->assertSame(1, substr_count($out, needle: 'General'));
		$this->assertMatchesRegularExpression('/General.*commands.*help.*plain/s', $out);
		$this->assertStringContainsString('bar:stuff', $out);
	}

	public function testRunFailingCommand(): void
	{
		$runner = $this->getRunner();

		$this->expectOutputRegex("/Error while.*'err'.*Red herring/s");
		$runner->run(['run', 'err']);
	}

	public function testFailingCommandReportsTheErrorWithoutTraceback(): void
	{
		$out = new Buffer();
		$code = new Runner($this->getCommands(), new Io($out))->run(['run', 'err']);

		$this->assertSame(1, $code);
		$this->assertSame('', $out->output());
		$this->assertSame("Error while running command 'err':\n\nRed herring\n", $out->errorOutput());
	}

	public function testRunFailingCommandWithCustomPrefix(): void
	{
		$runner = $this->getRunner();

		$this->expectOutputRegex("/Error while.*'err:err'.*Red herring/s");
		$runner->run(['run', 'err:err']);
	}

	public function testCommandValueErrorIsNotTreatedAsAmbiguous(): void
	{
		$commands = [new
			#[Command('boom', 'Fails with a ValueError')]
			class {
				public function __invoke(): int
				{
					throw new ValueError('Command failure', 1);
				}
			}];
		$out = new Buffer();
		$code = new Runner($commands, new Io($out))->run(['run', 'boom']);

		$this->assertSame(1, $code);
		$this->assertStringContainsString('Command failure', $out->errorOutput());
		$this->assertStringNotContainsString('Ambiguous command', $out->errorOutput());
	}

	public function testInvalidUsageFromTheCommandExitsWithTwo(): void
	{
		[$code, $out] = $this->runProbe(new
			#[Command('probe', 'Rejects its input')]
			class {
				public function __invoke(): int
				{
					throw new InvalidUsage('--apply and --test-run cannot be combined');
				}
			});

		$this->assertSame(2, $code);
		$this->assertSame(
			"Error while running command 'probe':\n\n--apply and --test-run cannot be combined\n",
			$out->errorOutput(),
		);
	}

	public function testRunFailingCommandWithDebug(): void
	{
		$runner = new Runner(
			$this->getCommands(),
			new Io(new Stdio('php://output', 'php://output')),
			debug: true,
		);

		$this->expectOutputRegex("/Error while.*'err'.*Red herring.*Traceback:\n#0 /s");
		$runner->run(['run', 'err']);
	}

	public function testRunReturnsSuccessCode(): void
	{
		ob_start();
		$code = $this->getRunner()->run(['run', 'drivel']);
		ob_get_clean();

		$this->assertSame(0, $code);
	}

	public function testRunReturnsFailureCodeFromCommand(): void
	{
		$runner = new Runner([new Fixtures\Failing()]);

		$this->assertSame(1, $runner->run(['run', 'fail']));
	}

	public function testRunReturnsFailureCodeOnException(): void
	{
		ob_start();
		$code = $this->getRunner()->run(['run', 'err']);
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
				public ?Args $seen = null;

				public function __invoke(Args $args): int
				{
					$this->seen = $args;

					return 0;
				}
			};
		[$code] = $this->runProbe($command);

		$this->assertSame(0, $code);
		$this->assertSame([], $command->seen?->positionals());
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
				public function __invoke(#[Arg('The when')] string $when, Io $io, Args $args): int
				{
					$io->echo("swapped {$when} " . (string) $args->positional(0));

					return 0;
				}
			}, 'now');

		$this->assertSame(0, $code);
		$this->assertSame('swapped now now', $out->output());
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
			"Command 'probe' parameter \$args must be declared as Args, Io, or an option group",
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
			"Command 'probe' parameter \$name must be declared as Args, Io, or an option group, or carry #[Arg] or #[Opt]\n",
			$out->errorOutput(),
		);
	}

	public function testRejectTerminalParameter(): void
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
			"Command 'probe' parameter \$io must be declared as Args, Io, or an option group",
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
			"Command 'probe' parameter \$args must be declared as Args, Io, or an option group",
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
			"Command 'probe' parameter \$io must be declared as Args, Io, or an option group",
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
			"Command 'probe' parameter \$io must be declared as Args, Io, or an option group",
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
		$err = (string) tempnam(sys_get_temp_dir(), prefix: 'cli');
		$runner = new Runner($this->getCommands(), new Io(new Stdio('php://output', $err)));

		ob_start();
		$code = $runner->run(['run', 'unknown']);
		$stdout = (string) ob_get_clean();

		$contents = (string) file_get_contents($err);
		unlink($err);

		$this->assertSame(2, $code);
		$this->assertSame('', $stdout);
		$this->assertStringContainsString('Command not found', $contents);
	}

	public function testCommandReceivesParsedArgs(): void
	{
		$runner = new Runner([new Fixtures\Greet()], new Io(new Stdio('php://output')));

		$this->expectOutputString('Hi, Ada');
		$runner->run(['run', 'greet', 'Ada', '--greeting=Hi']);
	}

	public function testCommandUsesArgDefaults(): void
	{
		$runner = new Runner([new Fixtures\Greet()], new Io(new Stdio('php://output')));

		$this->expectOutputString('Hello, World');
		$runner->run(['run', 'greet']);
	}

	public function testDeclaredHelpFlagReachesTheCommand(): void
	{
		// The runner does not intercept --help/-h; a command that declares
		// the flag reads it itself. Help stays on `run help <command>`.
		[$code, $out] = $this->runProbe(new
			#[Command('probe', 'Help probe')]
			class {
				public function __invoke(Io $io, #[Opt('Show this help', short: '-h')] bool $help = false): int
				{
					$io->echo($help ? 'own help' : 'no help');

					return 0;
				}
			}, '--help');

		$this->assertSame(0, $code);
		$this->assertSame('own help', $out->output());
	}

	public function testRunClassStringCommand(): void
	{
		$runner = new Runner(Fixtures\Plain::class, new Io(new Stdio('php://output')));

		$this->expectOutputString('Plain');
		$runner->run(['run', 'plain']);
	}

	public function testResolverRunsOnlyForTheInvokedCommand(): void
	{
		$resolved = [];
		$commands = [Fixtures\Plain::class, Fixtures\Greet::class];
		$resolve = static function (string $class) use (&$resolved): object {
			$resolved[] = $class;

			return new $class();
		};

		foreach ([['run'], ['run', 'help'], ['run', 'commands'], ['run', 'help', 'greet']] as $argv) {
			$out = new Buffer();

			$this->assertSame(0, new Runner($commands, new Io($out), resolve: $resolve)->run($argv));
			$this->assertNotSame('', $out->output());
			$this->assertSame([], $resolved);
		}

		$this->assertSame(
			2,
			new Runner($commands, new Io(new Buffer()), resolve: $resolve)->run(['run', 'greet', '--unknown']),
		);
		$this->assertSame([], $resolved);

		$out = new Buffer();

		$this->assertSame(0, new Runner($commands, new Io($out), resolve: $resolve)->run(['run', 'greet', 'Ada']));
		$this->assertSame('Hello, Ada', $out->output());
		$this->assertSame([Fixtures\Greet::class], $resolved);
	}

	public function testResolverInjectsIoIntoConstructor(): void
	{
		$out = new Buffer();
		$io = new Io($out);
		$runner = new Runner(
			Fixtures\InjectedGreet::class,
			$io,
			resolve: static fn(string $class): object => new $class($io),
		);

		$this->assertSame(0, $runner->run(['run', 'greet:injected', 'Ada']));
		$this->assertSame('Hello, Ada', $out->output());
	}

	public function testResolverFailureIsReported(): void
	{
		$out = new Buffer();
		$runner = new Runner(
			Fixtures\Greet::class,
			new Io($out),
			resolve: static fn(string $class): object => throw new RuntimeException('Dependencies unavailable'),
		);

		$this->assertSame(1, $runner->run(['run', 'greet']));
		$this->assertStringContainsString('Dependencies unavailable', $out->errorOutput());
	}

	public function testRunAnonymousClassCommand(): void
	{
		$commands = [new
			#[Command('cache:clear', 'Clears the cache')]
			class {
				public function __invoke(Io $out, #[Arg('What to clear')] string $what): int
				{
					$out->echo("cleared {$what}");

					return 0;
				}
			}];
		$runner = new Runner($commands, new Io(new Stdio('php://output')));

		ob_start();
		$code = $runner->run(['run', 'cache:clear', 'now']);
		$stdout = (string) ob_get_clean();

		$this->assertSame(0, $code);
		$this->assertSame('cleared now', $stdout);
	}

	public function testAnonymousClassCommandAppearsInHelp(): void
	{
		$commands = [new
			#[Command('cache:clear', 'Clears the cache')]
			class {
				public function __invoke(): int
				{
					return 0;
				}
			}];
		$runner = new Runner($commands, new Io(new Stdio('php://output')));

		$this->expectOutputRegex('/Cache.*cache:.*clear.*Clears the cache/s');
		$runner->run(['run']);
	}

	public function testFactorySubclassMayRenameParameters(): void
	{
		// The registered class defines the command line; the override
		// receives the values by position.
		$factory = static fn(): Fixtures\Greet => new class extends Fixtures\Greet {
			#[\Override]
			public function __invoke(Io $io, string $who = 'World', string $salutation = 'Hey'): int
			{
				$io->echo("{$salutation}, {$who}!");

				return 0;
			}
		};
		$commands = [Fixtures\Greet::class => $factory];

		$out = new Buffer();
		$this->assertSame(0, new Runner($commands, new Io($out))->run(['run', 'greet', 'Ada', '--greeting=Hi']));
		$this->assertSame('Hi, Ada!', $out->output());

		// Absent input leaves the override's own defaults in place.
		$out = new Buffer();
		$this->assertSame(0, new Runner($commands, new Io($out))->run(['run', 'greet']));
		$this->assertSame('Hey, World!', $out->output());
	}

	public function testFactoryRunsOnlyForTheInvokedCommand(): void
	{
		$called = false;
		$factory = static function () use (&$called): Fixtures\Greet {
			$called = true;

			return new Fixtures\Greet();
		};
		$commands = [Fixtures\Greet::class => $factory];

		ob_start();
		new Runner($commands, new Io(new Stdio('php://output')))->run(['run', 'help']);
		ob_get_clean();

		$this->assertFalse($called);

		ob_start();
		new Runner($commands, new Io(new Stdio('php://output')))->run(['run', 'greet']);
		ob_get_clean();

		$this->assertTrue($called);
	}

	public function testUninvokableCommandFails(): void
	{
		$runner = new Runner(
			Fixtures\Uninvokable::class,
			new Io(new Stdio('php://output', 'php://output')),
		);

		ob_start();
		$code = $runner->run(['run', 'broken']);
		$stdout = (string) ob_get_clean();

		$this->assertSame(1, $code);
		$this->assertStringContainsString("Command 'broken' is not callable", $stdout);
	}

	public function testHelpOptionRendersEqualsNotation(): void
	{
		$runner = new Runner([new Fixtures\HelpVariants()], new Io(new Stdio('php://output')));

		ob_start();
		$runner->run(['run', 'help', 'variants']);
		$raw = (string) ob_get_clean();
		$out = (string) preg_replace('/\033\[[0-9;]*m/', replacement: '', subject: $raw);

		$this->assertStringContainsString('-v, --verbose', $out);
		$this->assertStringContainsString('--prune', $out);
		$this->assertStringContainsString('-h=<host>, --host=<host>', $out);
		$this->assertStringContainsString('--release=<tag>', $out);
		$this->assertStringContainsString('-w[=<file>], --watch[=<file>]', $out);
	}
}
