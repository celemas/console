<?php

declare(strict_types=1);

namespace Celema\Console\Tests\Fixtures;

use Celema\Console\Args;
use Celema\Console\Command;
use Celema\Console\Io;
use Celema\Console\Opt;

#[Command('foo:stuff', "Prints Foo's stuff to stdout")]
#[Opt(
	'--stuff',
	'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam '
	. 'nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, '
	. 'sed diam voluptua. At vero eos et accusam et justo duo dolores et ea '
	. 'rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem '
	. 'ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing '
	. 'elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna '
	. 'aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo '
	. 'dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus '
	. 'est Lorem ipsum dolor sit amet.',
	short: '-s',
	value: 'stuff',
)]
class FooStuff
{
	public function __invoke(Args $args, Io $output): int
	{
		$output->echo("Foo's stuff");

		return 0;
	}
}
