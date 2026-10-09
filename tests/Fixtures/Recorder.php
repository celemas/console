<?php

declare(strict_types=1);

namespace Celema\Console\Tests\Fixtures;

use Celema\Console\Terminal;
use Override;

/**
 * Records what an Io does with a terminal that colors only its error
 * output.
 */
final class Recorder implements Terminal
{
	/** @var list<array{string, bool}> The texts and their error flags. */
	public array $writes = [];

	/** @var list<bool> The hidden flags of the reads. */
	public array $reads = [];

	/** @param list<string> $answers */
	public function __construct(
		private array $answers = [],
	) {}

	#[Override]
	public function write(string $text, bool $error = false): void
	{
		$this->writes[] = [$text, $error];
	}

	#[Override]
	public function read(bool $hidden = false): ?string
	{
		$this->reads[] = $hidden;

		return array_shift($this->answers);
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
