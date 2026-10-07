<?php

declare(strict_types=1);

namespace Celema\Console\Tests\Fixtures;

use Celema\Console\Arg;
use Celema\Console\Command;
use Celema\Console\Opt;

#[Command('defaults')]
class Defaults
{
	/**
	 * @param list<string> $paths
	 * @param list<string> $tag
	 */
	// One parameter per declared argument and option.
	// @mago-expect lint:excessive-parameter-list
	public function __invoke(
		#[Arg('Output format')]
		Format $format = Format::Csv,
		#[Arg('Paths to scan')]
		array $paths = ['.'],
		#[Opt('Rows per batch', short: '-b')]
		int $batch = 500,
		#[Opt]
		float $ratio = 0.5,
		#[Opt('Connection')]
		string $conn = 'sqlite',
		#[Opt('Severity')]
		Level $level = Level::High,
		#[Opt('Row limit')]
		?int $limit = null,
		#[Opt('Prefix')]
		string $prefix = '',
		#[Opt('Tags')]
		array $tag = [],
		#[Opt('Worker count', bare: '1')]
		?int $worker = null,
		#[Opt]
		bool $force = false,
	): int {
		return 0;
	}
}
