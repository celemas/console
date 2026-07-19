<?php

declare(strict_types=1);

namespace Celema\Console\Tests\Fixtures;

use Celema\Console\Args;
use Celema\Console\Command;
use Celema\Console\Io;
use Celema\Console\Opt;

#[Command('aliases')]
#[Opt('--verbose', 'Verbose output', short: '-v')]
#[Opt('--watch', 'Files to watch', short: '-w', value: 'file')]
final class OptionAliases
{
	public function __invoke(Args $args, Io $io): int
	{
		$io->echo((string) json_encode([
			$args->has('--verbose'),
			$args->has('-v'),
			$args->opts('--watch'),
			$args->opts('-w'),
		]));

		return 0;
	}
}
