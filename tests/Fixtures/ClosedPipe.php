<?php

declare(strict_types=1);

namespace Celema\Console\Tests\Fixtures;

/**
 * A stream wrapper whose reader went away: every write fails with the
 * notice PHP raises for a broken pipe.
 */
final class ClosedPipe
{
	/** @var resource|null */
	public mixed $context = null;

	public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
	{
		return true;
	}

	public function stream_write(string $data): int
	{
		trigger_error('fwrite(): Write of ' . strlen($data) . ' bytes failed with errno=32 Broken pipe', E_USER_NOTICE);

		return 0;
	}
}
