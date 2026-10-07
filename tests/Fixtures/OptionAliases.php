<?php

declare(strict_types=1);

namespace Celema\Console\Tests\Fixtures;

use Celema\Console\Arg;
use Celema\Console\Args;
use Celema\Console\Command;
use Celema\Console\Io;
use Celema\Console\Opt;

#[Command('aliases')]
final class OptionAliases
{
	/** @param list<string> $watch */
	public function __invoke(
		Args $args,
		Io $io,
		#[Arg('Extra tokens')]
		string $extra = '',
		#[Opt('Verbose output', short: '-v')]
		bool $verbose = false,
		#[Opt('Files to watch', short: '-w', value: 'file')]
		array $watch = [],
	): int {
		$io->echo((string) json_encode([
			$args->has('--verbose'),
			$args->has('-v'),
			$args->opts('--watch'),
			$args->opts('-w'),
			$verbose,
			$watch,
		]));

		return 0;
	}
}
