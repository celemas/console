<?php

declare(strict_types=1);

namespace Celema\Console\Tests;

use Celema\Console\Opt;
use PHPUnit\Framework\Attributes\DataProvider;
use ValueError;

class OptTest extends TestCase
{
	public function testValidDeclarations(): void
	{
		$plain = new Opt('A flag');
		$full = new Opt('Files', short: '-w', value: 'file', bare: '.');

		$this->assertSame('', $plain->short);
		$this->assertSame('-w', $full->short);
	}

	public static function invalidShortProvider(): array
	{
		return [['w'], ['-'], ['--w'], ['-w=x'], ['-w space']];
	}

	#[DataProvider('invalidShortProvider')]
	public function testRejectInvalidShortName(string $short): void
	{
		$this->expectException(ValueError::class);
		$this->expectExceptionMessage("Invalid short option name '{$short}'");

		new Opt('Files', short: $short);
	}
}
