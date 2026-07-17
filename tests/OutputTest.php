<?php

declare(strict_types=1);

namespace Celemas\Cli\Tests;

use Celemas\Cli\Output;

class OutputTest extends TestCase
{
	protected function tearDown(): void
	{
		putenv('COLUMNS');
		parent::tearDown();
	}

	public function testForegroundColors(): void
	{
		putenv('FORCE_COLOR=1');
		$output = new Output('php://output');

		$this->assertSame("\033[0;30mtest\033[0m", $output->color('test', 'black'));
		$this->assertSame("\033[1;30mtest\033[0m", $output->color('test', 'gray'));
		$this->assertSame("\033[1;30mtest\033[0m", $output->color('test', 'grey'));
		$this->assertSame("\033[0;31mtest\033[0m", $output->color('test', 'red'));
		$this->assertSame("\033[1;31mtest\033[0m", $output->color('test', 'lightred'));
		$this->assertSame("\033[0;32mtest\033[0m", $output->color('test', 'green'));
		$this->assertSame("\033[1;32mtest\033[0m", $output->color('test', 'lightgreen'));
		$this->assertSame("\033[0;33mtest\033[0m", $output->color('test', 'brown'));
		$this->assertSame("\033[1;33mtest\033[0m", $output->color('test', 'yellow'));
		$this->assertSame("\033[0;34mtest\033[0m", $output->color('test', 'blue'));
		$this->assertSame("\033[1;34mtest\033[0m", $output->color('test', 'lightblue'));
		$this->assertSame("\033[0;35mtest\033[0m", $output->color('test', 'magenta'));
		$this->assertSame("\033[1;35mtest\033[0m", $output->color('test', 'lightmagenta'));
		$this->assertSame("\033[0;35mtest\033[0m", $output->color('test', 'purple'));
		$this->assertSame("\033[1;35mtest\033[0m", $output->color('test', 'lightpurple'));
		$this->assertSame("\033[0;36mtest\033[0m", $output->color('test', 'cyan'));
		$this->assertSame("\033[1;36mtest\033[0m", $output->color('test', 'lightcyan'));
		$this->assertSame("\033[0;37mtest\033[0m", $output->color('test', 'lightgray'));
		$this->assertSame("\033[0;37mtest\033[0m", $output->color('test', 'lightgrey'));
		$this->assertSame("\033[1;37mtest\033[0m", $output->color('test', 'white'));
		$this->assertSame('test', $output->color('test'));
		putenv('FORCE_COLOR');
	}

	public function testHasColorSupport(): void
	{
		putenv('FORCE_COLOR=1');
		$output = new Output('php://output');
		$this->assertSame("\033[0;31mtest\033[0m", $output->color('test', 'red'));
		putenv('NO_COLOR=1');
		$this->assertSame('test', $output->color('test', 'red'));
		putenv('NO_COLOR');
		putenv('FORCE_COLOR');
	}

	public function testBackgroundColors(): void
	{
		putenv('FORCE_COLOR=1');
		$output = new Output('php://output');

		$this->assertSame("\033[0;37;40mtest\033[0m", $output->color('test', 'lightgrey', 'black'));
		$this->assertSame("\033[1;37;41mtest\033[0m", $output->color('test', 'white', 'red'));
		$this->assertSame("\033[1;32;42mtest\033[0m", $output->color('test', 'lightgreen', 'green'));
		$this->assertSame("\033[1;33;43mtest\033[0m", $output->color('test', 'yellow', 'yellow'));
		$this->assertSame("\033[0;34;44mtest\033[0m", $output->color('test', 'blue', 'blue'));
		$this->assertSame("\033[1;35;45mtest\033[0m", $output->color('test', 'lightpurple', 'purple'));
		$this->assertSame("\033[0;35;45mtest\033[0m", $output->color('test', 'purple', 'magenta'));
		$this->assertSame("\033[0;36;46mtest\033[0m", $output->color('test', 'cyan', 'cyan'));
		$this->assertSame("\033[1;37;47mtest\033[0m", $output->color('test', 'white', 'white'));
		$this->assertSame("\033[1;37;47mtest\033[0m", $output->color('test', 'white', 'gray'));
		$this->assertSame("\033[1;37;47mtest\033[0m", $output->color('test', 'white', 'grey'));

		$this->assertSame("\033[40mtest\033[0m", $output->color('test', background: 'black'));
		$this->assertSame("\033[41mtest\033[0m", $output->color('test', background: 'red'));
		$this->assertSame("\033[42mtest\033[0m", $output->color('test', background: 'green'));
		$this->assertSame("\033[43mtest\033[0m", $output->color('test', background: 'yellow'));
		$this->assertSame("\033[44mtest\033[0m", $output->color('test', background: 'blue'));
		$this->assertSame("\033[45mtest\033[0m", $output->color('test', background: 'purple'));
		$this->assertSame("\033[45mtest\033[0m", $output->color('test', background: 'magenta'));
		$this->assertSame("\033[46mtest\033[0m", $output->color('test', background: 'cyan'));
		$this->assertSame("\033[47mtest\033[0m", $output->color('test', background: 'white'));
		$this->assertSame("\033[47mtest\033[0m", $output->color('test', background: 'gray'));
		$this->assertSame("\033[47mtest\033[0m", $output->color('test', background: 'grey'));
		putenv('FORCE_COLOR');
	}

	public function testIndent(): void
	{
		putenv('COLUMNS');
		$output = new Output('php://output');
		$lorem =
			'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam '
			. 'nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, '
			. 'sed diam voluptua. At vero eos et accusam et justo duo dolores et ea '
			. 'rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem '
			. 'ipsum dolor sit amet.';
		$split = explode("\n", $output->indent($lorem, 4, 40));

		$this->assertSame('    Lorem ipsum dolor sit amet, consetetur', $split[0]);
		$this->assertSame('    At vero eos et accusam et justo duo', $split[4]);
	}

	public function testIndentUsesColumnsEnvAndCaches(): void
	{
		putenv('COLUMNS=40');
		$output = new Output('php://output');
		$text = 'Lorem ipsum dolor sit amet consetetur sadipscing';

		$first = explode("\n", $output->indent($text, 4));
		// The second call is served from the cached width.
		$second = explode("\n", $output->indent($text, 4));

		$this->assertSame($first, $second);
		$this->assertSame('    Lorem ipsum dolor sit amet', $first[0]);
	}
}
