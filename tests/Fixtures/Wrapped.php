<?php

declare(strict_types=1);

namespace Celema\Console\Tests\Fixtures;

use Celema\Console\Arg;
use Celema\Console\Command;
use Celema\Console\Opt;

#[Command('wrap')]
class Wrapped
{
	// With an indent of 8 the text width is 72: the first line fits
	// exactly, the second wraps its last word.
	public const string LINE72 = 'abcde abcde abcde abcde abcde abcde abcde abcde abcde abcde abcde abcdef';
	public const string LINE71 = 'abcde abcde abcde abcde abcde abcde abcde abcde abcde abcde abcde abcde';
	public const string DESCRIPTION = self::LINE72 . "\n" . self::LINE71 . ' x';

	public function __invoke(
		#[Arg(self::DESCRIPTION)]
		string $target,
		#[Opt(self::DESCRIPTION)]
		bool $long = false,
	): int {
		return 0;
	}
}
