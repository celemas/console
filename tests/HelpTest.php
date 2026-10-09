<?php

declare(strict_types=1);

namespace Celema\Console\Tests;

use Celema\Console\Arg;
use Celema\Console\Buffer;
use Celema\Console\Command;
use Celema\Console\Help;
use Celema\Console\Io;
use Celema\Console\Stdio;
use Celema\Console\Tests\Fixtures\Defaults;
use Celema\Console\Tests\Fixtures\HelpVariants;
use Celema\Console\Tests\Fixtures\Plain;
use Celema\Console\Tests\Fixtures\Wrapped;
use PHPUnit\Framework\Attributes\BackupGlobals;

class HelpTest extends TestCase
{
	public function testShowForRendersVariadicArguments(): void
	{
		$help = new Help(new Io(new Stdio('php://output')), 'run');
		$command = new
			#[Command('copy', 'Copies files')]
			class {
				/** @param list<string> $files */
				public function __invoke(
					#[Arg('The target')]
					string $target,
					#[Arg('The files')]
					array $files = [],
				): int {
					return 0;
				}
			};

		ob_start();
		$help->showFor($command);
		$raw = (string) ob_get_clean();
		$out = (string) preg_replace('/\033\[[0-9;]*m/', replacement: '', subject: $raw);

		$this->assertStringContainsString('php run copy <target> [<files>...]', $out);
		$this->assertStringContainsString("Arguments:\n    <target>\n        The target", $out);
		$this->assertStringContainsString("<files>...\n        The files", $out);
	}

	#[BackupGlobals(true)]
	public function testShowForDefaultsToTheServerScriptName(): void
	{
		$_SERVER['argv'] = ['bin/console'];
		$buffer = new Buffer();
		$io = new Io($buffer);
		new Help($io)->showFor(Plain::class);

		$this->assertStringContainsString('php bin/console plain', $buffer->output());
	}

	public function testShowForRendersOptionsFromAttributes(): void
	{
		$help = new Help(new Io(new Stdio('php://output')), 'run');

		ob_start();
		$help->showFor(new HelpVariants());
		$raw = (string) ob_get_clean();
		$out = (string) preg_replace('/\033\[[0-9;]*m/', replacement: '', subject: $raw);

		$this->assertStringContainsString('php run help:variants <file> [<target>] [options]', $out);
		$this->assertStringContainsString('-v, --verbose', $out);
		$this->assertStringContainsString('-w[=<file>], --watch[=<file>]', $out);
		$this->assertStringContainsString('Host to bind to [default: localhost]', $out);
		$this->assertStringContainsString("Arguments:\n    <file>\n        The file to process", $out);
		$this->assertStringContainsString("<target>\n        Where the result ends up", $out);
	}

	public function testShowForRendersDefaultsAndChoices(): void
	{
		$buffer = new Buffer();
		$io = new Io($buffer);

		new Help($io, 'run')->showFor(Defaults::class);

		$this->assertSame(
			<<<'TEXT'
				Usage:
				  php run defaults [<format>] [<paths>...] [options]

				Arguments:
				    <format>
				        Output format [choices: csv, json] [default: csv]
				    <paths>...
				        Paths to scan

				Options:
				    -b=<batch>, --batch=<batch>
				        Rows per batch [default: 500]
				    --ratio=<ratio>
				        [default: 0.5]
				    --conn=<conn>
				        Connection [default: sqlite]
				    --level=<level>
				        Severity [choices: 1, 2] [default: 2]
				    --limit=<limit>
				        Row limit
				    --prefix=<prefix>
				        Prefix
				    --tag=<tag>
				        Tags
				    --worker[=<worker>]
				        Worker count
				    --force

				TEXT,
			$buffer->output(),
		);
	}

	public function testShowForClassWithoutOptions(): void
	{
		$help = new Help(new Io(new Stdio('php://output')), 'run');

		ob_start();
		$help->showFor(Plain::class);
		$raw = (string) ob_get_clean();
		$out = (string) preg_replace('/\033\[[0-9;]*m/', replacement: '', subject: $raw);

		$this->assertStringContainsString('An ungrouped command', $out);
		$this->assertStringContainsString('php run plain', $out);
		$this->assertStringNotContainsString('Options:', $out);
		$this->assertStringNotContainsString('Arguments:', $out);
	}

	public function testShowWrapsDescriptionsAtEightyColumns(): void
	{
		$buffer = new Buffer(width: 100);
		new Help(new Io($buffer), 'run')->showFor(Wrapped::class);

		$block = '        ' . Wrapped::LINE72 . "\n        " . Wrapped::LINE71 . "\n        x\n";
		$this->assertSame(
			"Usage:\n  php run wrap <target> [options]\n"
				. "\nArguments:\n    <target>\n{$block}"
				. "\nOptions:\n    --long\n{$block}",
			$buffer->output(),
		);
	}
}
