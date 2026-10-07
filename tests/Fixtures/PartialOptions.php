<?php

declare(strict_types=1);

namespace Celema\Console\Tests\Fixtures;

use Celema\Console\Opt;

final readonly class PartialOptions
{
	public function __construct(
		#[Opt('Reduce output')]
		public bool $quiet = false,
		public string $mode = 'fast',
	) {}
}
