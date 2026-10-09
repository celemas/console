<?php

declare(strict_types=1);

namespace Celema\Console;

use ValueError;

/**
 * Renders rows of columns aligned on their visible width — markup
 * tags and multibyte characters don't count, so cells may carry
 * markup: `<green>1,075</green>`.
 *
 * Columns size to their widest cell and are separated by two spaces;
 * `rule()` inserts a separator line spanning the table. Cells don't
 * wrap: a table wider than the terminal simply overflows. Write the
 * result as a template:
 *
 *     $table = new Table(align: [Align::Left, Align::Right]);
 *     $table->row(['<strong>Language</strong>', '<strong>Lines</strong>']);
 *     $table->rule();
 *     $table->row(['PHP', '1,075']);
 *     $io->write($table->render());
 *
 * @api
 */
final class Table
{
	private readonly Markup $markup;

	/** @var list<list<string>|string> Cell rows, or a rule's char. */
	private array $rows = [];

	/** @param list<Align> $align Per column; further columns align left. */
	public function __construct(
		private readonly array $align = [],
	) {
		$this->markup = new Markup();
	}

	/** @param list<string> $cells A short row leaves the rest empty. */
	public function row(array $cells): void
	{
		$this->rows[] = $cells;
	}

	public function rule(string $char = '─'): void
	{
		if ($this->markup->width($char) < 1) {
			throw new ValueError("Rule char '{$char}' has no visible width");
		}

		$this->rows[] = $char;
	}

	/**
	 * The rendered table, lines separated and terminated by `\n`.
	 */
	public function render(): string
	{
		$widths = [];

		foreach ($this->rows as $row) {
			if (is_string($row)) {
				continue;
			}

			foreach ($row as $i => $cell) {
				$widths[$i] = max($widths[$i] ?? 0, $this->markup->width($cell));
			}
		}

		$total = array_sum($widths) + (2 * max(0, count($widths) - 1));
		$out = '';

		foreach ($this->rows as $row) {
			if (is_string($row)) {
				$out .= str_repeat($row, intdiv($total, $this->markup->width($row)));
			} else {
				$cells = [];

				foreach ($widths as $i => $width) {
					$cells[] = $this->markup->pad($row[$i] ?? '', $width, $this->align[$i] ?? Align::Left);
				}

				$out .= rtrim(implode('  ', $cells));
			}

			$out .= "\n";
		}

		return $out;
	}
}
