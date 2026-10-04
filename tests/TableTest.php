<?php

declare(strict_types=1);

namespace Celema\Console\Tests;

use Celema\Console\Align;
use Celema\Console\BufferedIo;
use Celema\Console\Table;
use ValueError;

class TableTest extends TestCase
{
	public function testRendersAlignedColumns(): void
	{
		$table = new Table(align: [Align::Left, Align::Right]);
		$table->row(['Language', 'Files']);
		$table->rule();
		$table->row(['PHP', '11']);

		$this->assertSame(
			"Language  Files\n───────────────\nPHP          11\n",
			$table->render(),
		);
	}

	public function testColumnsSizeToTheVisibleWidth(): void
	{
		$table = new Table(align: [Align::Left, Align::Right]);
		$table->row(['<strong>Straße</strong>', '<strong>Nr</strong>']);
		$table->row(['Ring', '7']);

		$out = new BufferedIo();
		$out->echo($table->render());

		$this->assertSame("Straße  Nr\nRing     7\n", $out->output());
	}

	public function testFurtherColumnsAlignLeftAndCenterAligns(): void
	{
		$table = new Table(align: [Align::Center]);
		$table->row(['a', 'b']);
		$table->row(['ccc', 'ddd']);

		$this->assertSame(" a   b\nccc  ddd\n", $table->render());
	}

	public function testShortRowsLeaveTheRestEmpty(): void
	{
		$table = new Table();
		$table->row(['total', '42']);
		$table->row(['note']);

		$this->assertSame("total  42\nnote\n", $table->render());
	}

	public function testRuleUsesTheVisibleCharWidth(): void
	{
		$table = new Table();
		$table->row(['abcd', 'ef']);
		$table->rule('═');
		$table->rule('─ ');

		$this->assertSame("abcd  ef\n════════\n─ ─ ─ ─ \n", $table->render());
	}

	public function testLeadingRuleSpansTheColumnsAndEmptyColumnsTakeNoWidth(): void
	{
		$table = new Table();
		$table->rule('-');
		$table->row(['a', '', 'ccc']);
		$table->row(['bb']);

		$this->assertSame("---------\na     ccc\nbb\n", $table->render());
	}

	public function testSingleColumnRuleHasNoSeparatorWidth(): void
	{
		$table = new Table();
		$table->row(['abc']);
		$table->rule('=');

		$this->assertSame("abc\n===\n", $table->render());
	}

	public function testRuleWithoutRowsRendersAnEmptyLine(): void
	{
		$table = new Table();
		$table->rule('=');

		$this->assertSame("\n", $table->render());
	}

	public function testEmptyTableRendersNothing(): void
	{
		$this->assertSame('', new Table()->render());
	}

	public function testRuleCharWithoutVisibleWidthThrows(): void
	{
		$this->expectException(ValueError::class);
		$this->expectExceptionMessage("Rule char '<dim></dim>' has no visible width");

		new Table()->rule('<dim></dim>');
	}
}
