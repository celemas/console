<?php

declare(strict_types=1);

namespace Celemas\Cli\Tests\Fixtures;

use Celemas\Cli\Args;
use Celemas\Cli\Command;

class FooStuff extends Command
{
	protected string $name = 'stuff';
	protected string $group = 'Foo';
	protected string $description = "Prints Foo's stuff to stdout";

	public function run(Args $args): int
	{
		$this->echo("Foo's stuff");

		return self::SUCCESS;
	}

	public function help(): void
	{
		$this->helpHeader(withOptions: true);
		$desc =
			'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam '
			. 'nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, '
			. 'sed diam voluptua. At vero eos et accusam et justo duo dolores et ea '
			. 'rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem '
			. 'ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing '
			. 'elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna '
			. 'aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo '
			. 'dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus '
			. 'est Lorem ipsum dolor sit amet.';
		$this->helpOption('--stuff', $desc, short: '-s', value: 'stuff');
	}
}
