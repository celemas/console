<?php

declare(strict_types=1);

namespace Celema\Console\Tests;

use Celema\Console\Arg;
use Celema\Console\Command;
use Celema\Console\Help;
use Celema\Console\Io;
use Celema\Console\Tests\Fixtures\HelpVariants;
use Celema\Console\Tests\Fixtures\Plain;

class HelpTest extends TestCase
{
	public function testShowForRendersVariadicArguments(): void
	{
		$_SERVER['argv'] = ['run', 'copy'];
		$help = new Help(new Io('php://output'));
		$command = new
			#[Command('copy', 'Copies files')]
			#[Arg('target', 'The target')]
			#[Arg('files', 'The files', optional: true, variadic: true)]
			class {
				public function __invoke(): int
				{
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

	public function testShowForRendersOptionsFromAttributes(): void
	{
		$_SERVER['argv'] = ['run', 'help:variants', '--help'];
		$help = new Help(new Io('php://output'));

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

	public function testShowForClassWithoutOptions(): void
	{
		$_SERVER['argv'] = ['run'];
		$help = new Help(new Io('php://output'));

		ob_start();
		$help->showFor(Plain::class);
		$raw = (string) ob_get_clean();
		$out = (string) preg_replace('/\033\[[0-9;]*m/', replacement: '', subject: $raw);

		$this->assertStringContainsString('An ungrouped command', $out);
		$this->assertStringContainsString('php run plain', $out);
		$this->assertStringNotContainsString('Options:', $out);
	}
}
