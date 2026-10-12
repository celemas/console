<?php

declare(strict_types=1);

namespace Celema\Console\Tests;

use Celema\Console\Command;
use Celema\Console\Tests\Fixtures\Erring;
use Celema\Console\Tests\Fixtures\FooStuff;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;
use ValueError;

class CommandTest extends TestCase
{
	public function testMetadataParsing(): void
	{
		$meta = new Command('DB:Add-Migration', 'Initialize a new migration', group: 'Database');

		$this->assertSame('add-migration', $meta->name);
		$this->assertSame('db', $meta->prefix);
		$this->assertSame('db:add-migration', $meta->full());
		$this->assertSame('Database', $meta->title());
		$this->assertSame('Initialize a new migration', $meta->description);
	}

	public function testTitleDerivedFromPrefix(): void
	{
		$meta = new Command('foo:stuff');

		$this->assertSame('Foo', $meta->title());
	}

	public function testUngroupedCommand(): void
	{
		$meta = new Command('plain');

		$this->assertSame('plain', $meta->name);
		$this->assertSame('', $meta->prefix);
		$this->assertSame('plain', $meta->full());
		$this->assertSame('General', $meta->title());
	}

	public static function nameProvider(): array
	{
		return [
			['cache.clear', '', 'cache.clear'],
			['2026', '', '2026'],
			['db_v2:make_migration', 'db_v2', 'make_migration'],
			['I18n:Status-All', 'i18n', 'status-all'],
		];
	}

	#[DataProvider('nameProvider')]
	public function testNameCharacters(string $name, string $prefix, string $bare): void
	{
		$meta = new Command($name);

		$this->assertSame($prefix, $meta->prefix);
		$this->assertSame($bare, $meta->name);
	}

	public static function invalidNameProvider(): array
	{
		return [
			'empty' => [''],
			'empty prefix' => [':stuff'],
			'empty name after prefix' => ['foo:'],
			'second colon' => ['x:foo:bar'],
			'space' => ['cache clear'],
			'leading space' => [' foo'],
			'line break' => ["foo\n"],
			'equals sign' => ['a=b'],
			'option-like' => ['--version'],
			'dash-led prefix' => ['-db:up'],
			'dash-led name after prefix' => ['db:-up'],
			'non-ASCII letter' => ['über'],
		];
	}

	#[DataProvider('invalidNameProvider')]
	public function testRejectInvalidName(string $name): void
	{
		$this->expectException(ValueError::class);
		$this->expectExceptionMessage("Invalid command name '{$name}'");

		new Command($name);
	}

	public function testReadAttributeFromInstance(): void
	{
		$meta = Command::of(new Erring());

		$this->assertSame('err', $meta->name);
		$this->assertSame('err', $meta->prefix);
		$this->assertSame('Errors', $meta->title());
	}

	public function testReadAttributeFromClass(): void
	{
		$meta = Command::of(FooStuff::class);

		$this->assertSame('stuff', $meta->name);
		$this->assertSame('foo', $meta->prefix);
	}

	public function testMissingAttributeFails(): void
	{
		$this->expectException(ValueError::class);
		$this->expectExceptionMessage('has no #[Command] attribute');

		Command::of(stdClass::class);
	}
}
