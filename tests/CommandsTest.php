<?php

declare(strict_types=1);

namespace Celema\Console\Tests;

use Celema\Console\Command;
use Celema\Console\Commands;
use Celema\Console\Tests\Fixtures\BarStuff;
use Celema\Console\Tests\Fixtures\FooStuff;
use Celema\Console\Tests\Fixtures\Greet;
use Celema\Console\Tests\Fixtures\Plain;
use stdClass;
use ValueError;

class CommandsTest extends TestCase
{
	public function testInitEmptyThenAddInstance(): void
	{
		$commands = new Commands();
		$foo = new FooStuff();
		$commands->add($foo);

		$this->assertSame($foo, $commands->entries()[0]->command());
	}

	public function testInitWithInstance(): void
	{
		$foo = new FooStuff();
		$commands = new Commands($foo);

		$this->assertSame($foo, $commands->entries()[0]->command());
		$this->assertSame('foo:stuff', $commands->entries()[0]->meta->full());
	}

	public function testInitWithArray(): void
	{
		$foo = new FooStuff();
		$bar = new BarStuff();
		$commands = new Commands([$foo, $bar]);

		$this->assertSame($foo, $commands->entries()[0]->command());
		$this->assertSame($bar, $commands->entries()[1]->command());
	}

	public function testRejectNestedArray(): void
	{
		$commands = new Commands(new FooStuff());

		$this->expectException(ValueError::class);
		$this->expectExceptionMessage('Invalid command registration');

		$commands->add([[new BarStuff()]]);
	}

	public function testAddCommands(): void
	{
		$foo = new FooStuff();
		$bar = new BarStuff();
		$commands = new Commands($foo);
		$commands->add(new Commands($bar));

		$this->assertSame($foo, $commands->entries()[0]->command());
		$this->assertSame($bar, $commands->entries()[1]->command());
	}

	public function testAddClassString(): void
	{
		$commands = new Commands(Plain::class);
		$entry = $commands->entries()[0];

		$this->assertSame('plain', $entry->meta->name);
		$this->assertInstanceOf(Plain::class, $entry->command());
		// The instance is created once and cached.
		$this->assertSame($entry->command(), $entry->command());
	}

	public function testAddFactory(): void
	{
		$called = false;
		$commands = new Commands([
			Greet::class => static function () use (&$called): Greet {
				$called = true;

				return new Greet();
			},
		]);
		$entry = $commands->entries()[0];

		// Metadata comes from the class; the factory runs on first use only.
		$this->assertSame('greet', $entry->meta->name);
		$this->assertFalse($called);
		$this->assertInstanceOf(Greet::class, $entry->command());
		$this->assertTrue($called);
	}

	public function testAddAnonymousClassCommand(): void
	{
		$commands = new Commands();
		$commands->add(new
			#[Command('cache:clear', 'Clears the cache')]
			class {
				public function __invoke(): int
				{
					return 0;
				}
			});
		$entry = $commands->entries()[0];

		$this->assertSame('cache:clear', $entry->meta->full());
		$this->assertSame('Clears the cache', $entry->meta->description);
		$this->assertSame([], $entry->opts());
	}

	public function testAddUnknownClassFails(): void
	{
		$this->expectException(ValueError::class);
		$this->expectExceptionMessage("Unknown command class 'Does\\Not\\Exist'");

		new Commands('Does\Not\Exist');
	}

	public function testAddUnknownFactoryClassFails(): void
	{
		$this->expectException(ValueError::class);
		$this->expectExceptionMessage("Unknown command class 'Does\\Not\\Exist'");

		new Commands(['Does\Not\Exist' => static fn(): Greet => new Greet()]);
	}

	public function testAddNonClosureFactoryFails(): void
	{
		$this->expectException(ValueError::class);
		$this->expectExceptionMessage('must be a closure');

		new Commands([Greet::class => new Greet()]);
	}

	public function testAddClosureFails(): void
	{
		$this->expectException(ValueError::class);
		$this->expectExceptionMessage('Closure commands are not supported');

		new Commands(static fn(): int => 0);
	}

	public function testAddInvalidItemFails(): void
	{
		$this->expectException(ValueError::class);
		$this->expectExceptionMessage('Invalid command registration');

		new Commands([42]);
	}

	public function testAddInstanceWithoutAttributeFails(): void
	{
		$this->expectException(ValueError::class);
		$this->expectExceptionMessage('has no #[Command] attribute');

		new Commands(new stdClass());
	}

	public function testFactoryReturningNonObjectFails(): void
	{
		$commands = new Commands([Greet::class => static fn(): mixed => null]);

		$this->expectException(ValueError::class);
		$this->expectExceptionMessage("Factory for command 'greet' must return a " . Greet::class);

		$commands->entries()[0]->command();
	}

	public function testFactoryReturningUnrelatedClassFails(): void
	{
		$commands = new Commands([Greet::class => static fn(): Plain => new Plain()]);

		$this->expectException(ValueError::class);
		$this->expectExceptionMessage("Factory for command 'greet' must return a " . Greet::class);

		$commands->entries()[0]->command();
	}
}
