<?php

declare(strict_types=1);

namespace Celema\Console\Tests\Fixtures;

use Celema\Console\Arg;
use Celema\Console\Args;
use Celema\Console\Command;
use Celema\Console\Io;
use Celema\Console\Opt;

#[Command('help:variants', 'Exercises help option rendering')]
#[Arg('file', 'The file to process')]
#[Arg('target', 'Where the result ends up', optional: true)]
#[Opt('--verbose', 'Enable verbose output', short: '-v')]
#[Opt('--prune', 'Drop obsolete entries')]
#[Opt('--host', 'Host to bind to', short: '-h', value: 'host', default: 'localhost')]
#[Opt('--release', 'Install a specific tag', value: 'tag')]
#[Opt('--watch', 'Optionally watch files', short: '-w', value: 'file', optionalValue: true)]
class HelpVariants
{
	public function __invoke(Args $args, Io $output): int
	{
		return 0;
	}
}
