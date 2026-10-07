<?php

declare(strict_types=1);

namespace Celema\Console\Tests;

use Celema\Console\Command;
use Celema\Console\Commands;
use Celema\Console\Tests\Fixtures\BarStuff;
use Celema\Console\Tests\Fixtures\FooStuff;
use Celema\Console\Tests\Fixtures\Greet;
use Celema\Console\Tests\Fixtures\Plain;
use PHPUnit\Framework\Attributes\DataProvider;
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

	public function testAddIsChainable(): void
	{
		$foo = new FooStuff();
		$bar = new BarStuff();
		$commands = new Commands()->add($foo)->add($bar);

		$this->assertSame($foo, $commands->entries()[0]->command());
		$this->assertSame($bar, $commands->entries()[1]->command());
	}

	public static function rejectedRegistrationProvider(): iterable
	{
		yield 'unknown class' => [[Plain::class, 'Missing\\Command'], "Unknown command class 'Missing\\Command'"];
		yield 'non-closure factory' => [[new BarStuff(), Greet::class => new Greet()], 'must be a closure'];
		yield 'invalid item' => [[new BarStuff(), 42], 'Invalid command registration'];
	}

	#[DataProvider('rejectedRegistrationProvider')]
	public function testRejectedAddRegistersNone(array $commands, string $message): void
	{
		$foo = new FooStuff();
		$collection = new Commands($foo);

		try {
			$collection->add($commands);
			$this->fail('The registration was accepted');
		} catch (ValueError $e) {
			$this->assertStringContainsString($message, $e->getMessage());
		}

		$this->assertCount(1, $collection->entries());
		$this->assertSame($foo, $collection->entries()[0]->command());
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

	public function testResolverAppliesToInitialAndAddedClasses(): void
	{
		$resolved = [];
		$commands = new Commands(
			[Greet::class],
			resolve: static function (string $class) use (&$resolved): object {
				$resolved[] = $class;

				return new $class();
			},
		);
		$commands->add(Plain::class);

		$this->assertSame([], $resolved);
		[$greet, $plain] = $commands->entries();
		$this->assertInstanceOf(Greet::class, $greet->command());
		$this->assertSame($greet->command(), $greet->command());
		$this->assertInstanceOf(Plain::class, $plain->command());
		$this->assertSame([Greet::class, Plain::class], $resolved);
	}

	public function testResolverAcceptsMethodCallable(): void
	{
		$resolver = new class {
			public function resolve(string $class): object
			{
				return new $class();
			}
		};
		$commands = new Commands(Plain::class, resolve: [$resolver, 'resolve']);

		$this->assertInstanceOf(Plain::class, $commands->entries()[0]->command());
	}

	public function testInstancesAndExplicitFactoriesBypassResolver(): void
	{
		$greet = new Greet();
		$plain = new Plain();
		$commands = new Commands(
			[$greet, Plain::class => static fn(): Plain => $plain],
			resolve: fn(string $class): object => $this->fail("Unexpected resolution of {$class}"),
		);

		$this->assertSame($greet, $commands->entries()[0]->command());
		$this->assertSame($plain, $commands->entries()[1]->command());
	}

	public function testMergedCommandsKeepTheirResolverAndCachedInstance(): void
	{
		$resolved = [];
		$source = new Commands(
			[Greet::class, Plain::class],
			resolve: static function (string $class) use (&$resolved): object {
				$resolved[] = $class;

				return new $class();
			},
		);
		$greet = $source->entries()[0]->command();
		$commands = new Commands(
			resolve: fn(string $class): object => $this->fail("Unexpected resolution of {$class}"),
		);
		$commands->add($source);

		$this->assertSame($greet, $commands->entries()[0]->command());
		$this->assertSame($source->entries()[1]->command(), $commands->entries()[1]->command());
		$this->assertSame([Greet::class, Plain::class], $resolved);
	}

	public function testResolverReturningNonObjectFails(): void
	{
		$commands = new Commands(Greet::class, resolve: static fn(string $class): mixed => null);

		$this->expectException(ValueError::class);
		$this->expectExceptionMessage('must return a ' . Greet::class);

		$commands->entries()[0]->command();
	}

	public function testResolverReturningUnrelatedClassFails(): void
	{
		$commands = new Commands(Greet::class, resolve: static fn(string $class): Plain => new Plain());

		$this->expectException(ValueError::class);
		$this->expectExceptionMessage('must return a ' . Greet::class);

		$commands->entries()[0]->command();
	}

	public function testInitWithSeveralFactories(): void
	{
		$commands = new Commands([
			Greet::class => static fn(): Greet => new Greet(),
			Plain::class => static fn(): Plain => new Plain(),
		]);

		$this->assertSame(
			['greet', 'plain'],
			array_map(static fn($entry): string => $entry->meta->name, $commands->entries()),
		);
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
		$this->assertSame([], $entry->signature()->options());
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
