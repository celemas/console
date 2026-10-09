<?php

declare(strict_types=1);

namespace Celema\Console;

/**
 * The device behind an Io: where its output goes, where its input comes
 * from, and what the device supports.
 *
 * Stdio talks to the process streams, Buffer captures everything for
 * tests.
 *
 * @api
 */
interface Terminal
{
	/**
	 * Writes the text as is, to the error output with `$error`.
	 */
	public function write(string $text, bool $error = false): void;

	/**
	 * Reads one line without its line break; null at the end of input.
	 *
	 * With `$hidden`, typing must not be visible. An implementation that
	 * cannot hide it on a device that would show it must throw instead
	 * of reading.
	 */
	public function read(bool $hidden = false): ?string;

	/**
	 * Whether the output, or with `$error` the error output, renders
	 * color codes.
	 */
	public function colors(bool $error = false): bool;

	/**
	 * Whether someone can see the prompts and answer them.
	 */
	public function interactive(): bool;

	/**
	 * The output width in columns.
	 */
	public function width(): int;
}
