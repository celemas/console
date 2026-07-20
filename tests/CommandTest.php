<?php

declare(strict_types=1);

namespace Celema\Console\Tests;

use Celema\Console\Command;
use Celema\Console\Opt;
use Celema\Console\Tests\Fixtures\Erring;
use Celema\Console\Tests\Fixtures\FooStuff;
use Celema\Console\Tests\Fixtures\Plain;
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

	public function testEmptyNameFails(): void
	{
		$this->expectException(ValueError::class);
		$this->expectExceptionMessage('Invalid command name');

		new Command('');
	}

	public function testEmptyPrefixFails(): void
	{
		$this->expectException(ValueError::class);
		$this->expectExceptionMessage('Invalid command name');

		new Command(':stuff');
	}

	public function testEmptyNameWithPrefixFails(): void
	{
		$this->expectException(ValueError::class);
		$this->expectExceptionMessage('Invalid command name');

		new Command('foo:');
	}

	public function testMultipleColonsFail(): void
	{
		$this->expectException(ValueError::class);
		$this->expectExceptionMessage("Invalid command name 'x:foo:bar'");

		new Command('x:foo:bar');
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

	public function testReadOptsFromClass(): void
	{
		$opts = Opt::of(FooStuff::class);

		$this->assertCount(1, $opts);
		$this->assertSame('--stuff', $opts[0]->long);
		$this->assertSame('-s', $opts[0]->short);
		$this->assertSame('stuff', $opts[0]->value);
		$this->assertFalse($opts[0]->optionalValue);
	}

	public function testReadOptsFromInstance(): void
	{
		$this->assertSame([], Opt::of(new Plain()));
	}
}
