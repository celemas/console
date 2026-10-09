<?php

declare(strict_types=1);

namespace Celema\Console\Tests\Fixtures;

use Celema\Console\Terminal;
use Override;

/**
 * Records the writes of a terminal that colors only its error output.
 */
final class ErrorColors implements Terminal
{
	/** @var list<array{string, bool}> */
	public array $writes = [];

	#[Override]
	public function write(string $text, bool $error = false): void
	{
		$this->writes[] = [$text, $error];
	}

	#[Override]
	public function read(bool $hidden = false): ?string
	{
		return null;
	}

	#[Override]
	public function colors(bool $error = false): bool
	{
		return $error;
	}

	#[Override]
	public function interactive(): bool
	{
		return false;
	}

	#[Override]
	public function width(): int
	{
		return 80;
	}
}
