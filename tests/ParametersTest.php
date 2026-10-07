<?php

declare(strict_types=1);

namespace Celema\Console\Tests;

use Celema\Console\Arg;
use Celema\Console\BufferedIo;
use Celema\Console\Command;
use Celema\Console\Commands;
use Celema\Console\Io;
use Celema\Console\Opt;
use Celema\Console\Runner;
use Celema\Console\Tests\Fixtures\Format;
use Celema\Console\Tests\Fixtures\Level;
use Celema\Console\Tests\Fixtures\PartialOptions;
use Celema\Console\Tests\Fixtures\ServeOptions;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;

class ParametersTest extends TestCase
{
	/** @return array{int, BufferedIo} */
	private function runProbe(object $command, string ...$args): array
	{
		$out = new BufferedIo();

		return [new Runner(new Commands([$command]), $out)->run(['run', 'probe', ...$args]), $out];
	}

	private static function typed(): object
	{
		return new
			#[Command('probe')]
			class {
				public array $seen = [];

				/** @param list<string> $tag */
				// One parameter per declared argument and option.
				// @mago-expect lint:excessive-parameter-list
				public function __invoke(
					#[Opt]
					int $batch = 500,
					#[Opt]
					float $ratio = 1.0,
					#[Opt]
					string $label = 'none',
					#[Opt]
					Format $format = Format::Csv,
					#[Opt]
					Level $level = Level::Low,
					#[Opt]
					?int $limit = null,
					#[Opt]
					bool $dryRun = false,
					#[Opt]
					array $tag = [],
				): int {
					$this->seen = get_defined_vars();

					return 0;
				}
			};
	}

	private static function arguments(): object
	{
		return new
			#[Command('probe')]
			class {
				public array $seen = [];

				// One parameter per declared argument and option.
				// @mago-expect lint:excessive-parameter-list
				public function __invoke(
					#[Arg]
					string $name,
					#[Arg]
					int $count,
					#[Arg]
					float $ratio,
					#[Arg]
					Format $format,
					#[Arg]
					Level $level,
					#[Arg]
					string $targetDir = 'out',
				): int {
					$this->seen = get_defined_vars();

					return 0;
				}
			};
	}

	public function testConvertsArgumentsToTheDeclaredTypes(): void
	{
		$command = self::arguments();
		[$code] = $this->runProbe($command, 'x', '42', '0.25', 'json', '2', 'dist');

		$this->assertSame(0, $code);
		$this->assertSame(
			[
				'name' => 'x',
				'count' => 42,
				'ratio' => 0.25,
				'format' => Format::Json,
				'level' => Level::High,
				'targetDir' => 'dist',
			],
			$command->seen,
		);
	}

	public function testOmittedOptionalArgumentKeepsItsDefault(): void
	{
		$command = self::arguments();
		[$code] = $this->runProbe($command, '--', 'x', '-7', '3', 'csv', '1');

		$this->assertSame(0, $code);
		$this->assertSame(-7, $command->seen['count']);
		$this->assertSame(3.0, $command->seen['ratio']);
		$this->assertSame('out', $command->seen['targetDir']);
	}

	public function testArgumentNamesAreKebabCase(): void
	{
		$command = new
			#[Command('probe')]
			class {
				public function __invoke(#[Arg] string $targetDir): int
				{
					return 0;
				}
			};
		[$code, $out] = $this->runProbe($command);

		$this->assertSame(2, $code);
		$this->assertStringContainsString("Missing required argument '<target-dir>'", $out->errorOutput());
	}

	public function testConvertsOptionsToTheDeclaredTypes(): void
	{
		$command = self::typed();
		[$code] = $this->runProbe(
			$command,
			'--batch=10',
			'--ratio=0.25',
			'--label=',
			'--format=json',
			'--level=2',
			'--limit=0',
			'--dry-run',
			'--tag=a',
			'--tag=b',
		);

		$this->assertSame(0, $code);
		$this->assertSame(
			[
				'batch' => 10,
				'ratio' => 0.25,
				'label' => '',
				'format' => Format::Json,
				'level' => Level::High,
				'limit' => 0,
				'dryRun' => true,
				'tag' => ['a', 'b'],
			],
			$command->seen,
		);
	}

	public function testAbsentOptionsKeepTheirDefaults(): void
	{
		$command = self::typed();
		[$code] = $this->runProbe($command);

		$this->assertSame(0, $code);
		$this->assertSame(
			[
				'batch' => 500,
				'ratio' => 1.0,
				'label' => 'none',
				'format' => Format::Csv,
				'level' => Level::Low,
				'limit' => null,
				'dryRun' => false,
				'tag' => [],
			],
			$command->seen,
		);
	}

	public static function invalidValueProvider(): array
	{
		return [
			'integer option' => [['--batch=many'], "Option '--batch' expects an integer, got 'many'"],
			'number option' => [['--ratio=half'], "Option '--ratio' expects a number, got 'half'"],
			'string enum option' => [['--format=xml'], "Option '--format' expects one of csv, json, got 'xml'"],
			'int enum option' => [['--level=3'], "Option '--level' expects one of 1, 2, got '3'"],
			'int enum option with padding' => [['--level=01'], "Option '--level' expects one of 1, 2, got '01'"],
			'repeated option' => [['--batch=1', '--batch=2'], "Option '--batch' accepts only one value"],
			'bare repeatable option' => [['--tag'], "Option '--tag' requires a value: --tag=<tag>"],
		];
	}

	/** @param list<string> $args */
	#[DataProvider('invalidValueProvider')]
	public function testRejectInvalidOptionValues(array $args, string $message): void
	{
		[$code, $out] = $this->runProbe(self::typed(), ...$args);

		$this->assertSame(2, $code);
		$this->assertStringContainsString($message, $out->errorOutput());
	}

	public function testRejectInvalidArgumentValues(): void
	{
		[$code, $out] = $this->runProbe(self::arguments(), 'x', 'many', '1', 'csv', '1');

		$this->assertSame(2, $code);
		$this->assertStringContainsString("Argument '<count>' expects an integer, got 'many'", $out->errorOutput());

		[$code, $out] = $this->runProbe(self::arguments(), 'x', '1', '1', 'csv', 'high');

		$this->assertSame(2, $code);
		$this->assertStringContainsString("Argument '<level>' expects one of 1, 2, got 'high'", $out->errorOutput());
	}

	public function testBareValueMakesTheValueOptional(): void
	{
		$command = new
			#[Command('probe')]
			class {
				public array $seen = [];

				public function __invoke(#[Opt(bare: '1')] ?int $worker = null): int
				{
					$this->seen[] = $worker;

					return 0;
				}
			};

		$this->runProbe($command);
		$this->runProbe($command, '--worker');
		$this->runProbe($command, '--worker=4');

		$this->assertSame([null, 1, 4], $command->seen);
	}

	public static function repeatedBareOptionProvider(): array
	{
		return [
			'bare twice' => [['--worker', '--worker']],
			'bare and short alias' => [['--worker', '-w']],
			'bare and valued' => [['-w', '--worker=4']],
		];
	}

	/** @param list<string> $args */
	#[DataProvider('repeatedBareOptionProvider')]
	public function testRejectRepeatedOptionWithBareValue(array $args): void
	{
		[$code, $out] = $this->runProbe(new
			#[Command('probe')]
			class {
				public function __invoke(#[Opt(short: '-w', bare: '1')] ?int $worker = null): int
				{
					return 0;
				}
			}, ...$args);

		$this->assertSame(2, $code);
		$this->assertStringContainsString("Option '--worker' accepts only one value", $out->errorOutput());
	}

	public function testVariadicArgumentKeepsItsDefaultWithoutPositionals(): void
	{
		$command = new
			#[Command('probe')]
			class {
				public array $seen = [];

				/** @param list<string> $paths */
				public function __invoke(#[Arg] array $paths = ['.']): int
				{
					$this->seen = $paths;

					return 0;
				}
			};

		$this->runProbe($command);
		$this->assertSame(['.'], $command->seen);

		$this->runProbe($command, 'src', 'tests');
		$this->assertSame(['src', 'tests'], $command->seen);
	}

	public function testVariadicArgumentTakesThePositionalsAfterTheOthers(): void
	{
		$command = new
			#[Command('probe')]
			class {
				public array $seen = [];

				/** @param list<string> $files */
				public function __invoke(#[Arg] string $target, #[Arg] array $files): int
				{
					$this->seen = get_defined_vars();

					return 0;
				}
			};
		[$code] = $this->runProbe($command, 'dist', 'a.txt', 'b.txt');

		$this->assertSame(0, $code);
		$this->assertSame(['target' => 'dist', 'files' => ['a.txt', 'b.txt']], $command->seen);
	}

	private static function served(): object
	{
		return new
			#[Command('probe')]
			class {
				public ?ServeOptions $options = null;
				public bool $open = false;

				public function __invoke(ServeOptions $options, #[Opt('Open a browser')] bool $open = false): int
				{
					$this->options = $options;
					$this->open = $open;

					return 0;
				}
			};
	}

	public function testOptionGroupReceivesItsOptions(): void
	{
		$command = self::served();
		[$code] = $this->runProbe($command, '-H=0.0.0.0', '--port=8080', '--open');

		$this->assertSame(0, $code);
		$this->assertEquals(new ServeOptions(host: '0.0.0.0', port: 8080), $command->options);
		$this->assertTrue($command->open);
	}

	public function testOptionGroupKeepsItsDefaults(): void
	{
		$command = self::served();
		[$code] = $this->runProbe($command);

		$this->assertSame(0, $code);
		$this->assertEquals(new ServeOptions(), $command->options);
		$this->assertFalse($command->open);
	}

	public function testOptionGroupValuesAreValidated(): void
	{
		[$code, $out] = $this->runProbe(self::served(), '--port=http');

		$this->assertSame(2, $code);
		$this->assertStringContainsString("Option '--port' expects an integer, got 'http'", $out->errorOutput());
	}

	public function testHelpListsTheOptionsOfGroups(): void
	{
		$out = new BufferedIo();
		new Runner(new Commands([self::served()]), $out)->run(['run', 'help', 'probe']);

		$this->assertStringContainsString(
			"Options:\n    -H=<host>, --host=<host>\n        Host to bind to [default: localhost]\n"
				. "    -p=<port>, --port=<port>\n        Port to listen on\n"
				. "    -q, --quiet\n        Reduce output\n"
				. "    --open\n        Open a browser\n",
			$out->output(),
		);
	}

	public function testCommandsCanBeCalledDirectlyWithNamedArguments(): void
	{
		$command = self::typed();

		$this->assertSame(0, $command(batch: 10, tag: ['a']));
		$this->assertSame(10, $command->seen['batch']);
		$this->assertSame(['a'], $command->seen['tag']);
	}

	public static function invalidDeclarationProvider(): array
	{
		return [
			'option without default' => [
				new
					#[Command('probe')]
					class {
						public function __invoke(#[Opt] int $batch): int
						{
							return 0;
						}
					},
				"Command 'probe' option '--batch' needs a default value",
			],
			'flag defaulting to true' => [
				new
					#[Command('probe')]
					class {
						public function __invoke(#[Opt] bool $watch = true): int
						{
							return 0;
						}
					},
				"Command 'probe' flag '--watch' must default to false",
			],
			'flag with value label' => [
				new
					#[Command('probe')]
					class {
						public function __invoke(#[Opt(value: 'x')] bool $force = false): int
						{
							return 0;
						}
					},
				"Command 'probe' flag '--force' takes no value",
			],
			'flag with bare value' => [
				new
					#[Command('probe')]
					class {
						public function __invoke(#[Opt(bare: 'x')] bool $force = false): int
						{
							return 0;
						}
					},
				"Command 'probe' flag '--force' takes no value",
			],
			'repeatable option with bare value' => [
				new
					#[Command('probe')]
					class {
						/** @param list<string> $tag */
						public function __invoke(#[Opt(bare: 'x')] array $tag = []): int
						{
							return 0;
						}
					},
				"Command 'probe' repeatable option '--tag' cannot have a bare value",
			],
			'bare value of the wrong type' => [
				new
					#[Command('probe')]
					class {
						public function __invoke(#[Opt(bare: 'all')] ?int $worker = null): int
						{
							return 0;
						}
					},
				"Command 'probe' option '--worker' needs an integer as bare value, got 'all'",
			],
			'bool argument' => [
				new
					#[Command('probe')]
					class {
						public function __invoke(#[Arg] bool $force): int
						{
							return 0;
						}
					},
				"Command 'probe' argument '<force>' cannot be a bool; declare a flag with #[Opt]",
			],
			'untyped parameter' => [
				new
					#[Command('probe')]
					class {
						/** @param mixed $name */
						public function __invoke(#[Arg] $name): int
						{
							return 0;
						}
					},
				"Command 'probe' parameter \$name must be declared as string, int, float, bool, array, or a backed enum",
			],
			'union type' => [
				new
					#[Command('probe')]
					class {
						public function __invoke(#[Opt] int|string $size = 1): int
						{
							return 0;
						}
					},
				"Command 'probe' parameter \$size must be declared as string, int, float, bool, array, or a backed enum",
			],
			'class type' => [
				new
					#[Command('probe')]
					class {
						public function __invoke(#[Arg] Io $io): int
						{
							return 0;
						}
					},
				"Command 'probe' parameter \$io must be declared as string, int, float, bool, array, or a backed enum",
			],
			'variadic parameter' => [
				new
					#[Command('probe')]
					class {
						public function __invoke(#[Arg] string ...$files): int
						{
							return 0;
						}
					},
				"Command 'probe' parameter \$files cannot be variadic; declare it as array",
			],
			'option group member without #[Opt]' => [
				new
					#[Command('probe')]
					class {
						public function __invoke(PartialOptions $options): int
						{
							return 0;
						}
					},
				"Command 'probe' option group " . PartialOptions::class . ' parameter $mode must carry #[Opt]',
			],
			'class without constructor' => [
				new
					#[Command('probe')]
					class {
						public function __invoke(stdClass $options): int
						{
							return 0;
						}
					},
				"Command 'probe' parameter \$options must be declared as Args, Io, or an option group, "
					. 'or carry #[Arg] or #[Opt]',
			],
			'class without options' => [
				new
					#[Command('probe')]
					class {
						public function __invoke(Command $meta): int
						{
							return 0;
						}
					},
				"Command 'probe' parameter \$meta must be declared as Args, Io, or an option group, "
					. 'or carry #[Arg] or #[Opt]',
			],
			'option in a group and the command' => [
				new
					#[Command('probe')]
					class {
						public function __invoke(ServeOptions $options, #[Opt] bool $quiet = false): int
						{
							return 0;
						}
					},
				"Command 'probe' declares the option name '--quiet' twice",
			],
			'both attributes' => [
				new
					#[Command('probe')]
					class {
						public function __invoke(#[Arg]
							#[Opt] string $name = ''): int
						{
							return 0;
						}
					},
				"Command 'probe' parameter \$name cannot carry both #[Arg] and #[Opt]",
			],
		];
	}

	#[DataProvider('invalidDeclarationProvider')]
	public function testRejectInvalidDeclarations(object $command, string $message): void
	{
		[$code, $out] = $this->runProbe($command);

		$this->assertSame(1, $code);
		$this->assertStringContainsString($message, $out->errorOutput());
	}
}
