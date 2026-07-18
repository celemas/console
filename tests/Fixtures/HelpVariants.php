<?php

declare(strict_types=1);

namespace Celema\Console\Tests\Fixtures;

use Celema\Console\Args;
use Celema\Console\Command;

class HelpVariants extends Command
{
	protected string $name = 'variants';
	protected string $group = 'Help';
	protected string $description = 'Exercises help option rendering';

	public function run(Args $args): int
	{
		return self::SUCCESS;
	}

	public function help(): void
	{
		$this->helpHeader(withOptions: true);
		$this->helpOption('--verbose', 'Enable verbose output', short: '-v');
		$this->helpOption('--prune', 'Drop obsolete entries');
		$this->helpOption('--host', 'Host to bind to', short: '-h', value: 'host');
		$this->helpOption('--release', 'Install a specific tag', value: 'tag');
		$this->helpOption(
			'--watch',
			'Optionally watch files',
			short: '-w',
			value: 'file',
			optionalValue: true,
		);
	}
}
