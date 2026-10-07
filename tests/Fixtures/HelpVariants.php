<?php

declare(strict_types=1);

namespace Celema\Console\Tests\Fixtures;

use Celema\Console\Arg;
use Celema\Console\Command;
use Celema\Console\Opt;

#[Command('help:variants', 'Exercises help option rendering')]
class HelpVariants
{
	// One parameter per declared argument and option.
	// @mago-expect lint:excessive-parameter-list
	public function __invoke(
		#[Arg('The file to process')]
		string $file,
		#[Arg('Where the result ends up')]
		string $target = '',
		#[Opt('Enable verbose output', short: '-v')]
		bool $verbose = false,
		#[Opt('Drop obsolete entries')]
		bool $prune = false,
		#[Opt('Host to bind to', short: '-h')]
		string $host = 'localhost',
		#[Opt('Install a specific tag', value: 'tag')]
		string $release = '',
		#[Opt('Optionally watch files', short: '-w', value: 'file', bare: '.')]
		string $watch = '',
	): int {
		return 0;
	}
}
