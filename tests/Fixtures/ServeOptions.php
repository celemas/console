<?php

declare(strict_types=1);

namespace Celema\Console\Tests\Fixtures;

use Celema\Console\Opt;

final readonly class ServeOptions
{
	public function __construct(
		#[Opt('Host to bind to', short: '-H')]
		public string $host = 'localhost',
		#[Opt('Port to listen on', short: '-p')]
		public ?int $port = null,
		#[Opt('Reduce output', short: '-q')]
		public bool $quiet = false,
	) {}
}
