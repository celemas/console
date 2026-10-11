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

	public static function shortProvider(): array
	{
		return [['-w'], ['-W'], ['-1'], ['-?'], ['-ä']];
	}

	#[DataProvider('shortProvider')]
	public function testShortNameIsOneCharacter(string $short): void
	{
		$this->assertSame($short, new Opt('Files', short: $short)->short);
	}

	public static function invalidShortProvider(): array
	{
		return [['w'], ['-'], ['--'], ['--w'], ['-vv'], ['-='], ['- '], ["-w\n"]];
	}

	#[DataProvider('invalidShortProvider')]
	public function testRejectInvalidShortName(string $short): void
	{
		$this->expectException(ValueError::class);
		$this->expectExceptionMessage("Short option name '{$short}' must be a dash and one character");

		new Opt('Files', short: $short);
	}
}
