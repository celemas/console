<?php

declare(strict_types=1);

namespace Celema\Console;

use Attribute;

/**
 * Declares an `__invoke()` parameter as a positional argument.
 *
 * The argument takes its name from the parameter, converted to kebab-case:
 * `$targetDir` renders as `<target-dir>`. Positionals are matched to the
 * arguments in declaration order and converted to the declared type:
 * `string`, `int`, `float`, or a backed enum. A parameter with a default
 * is optional and renders as `[<name>]`.
 *
 * An `array` parameter must be the last argument and takes the remaining
 * positionals as strings: at least one, or any number when it has a
 * default. It renders as `<name>...`.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class Arg
{
	public function __construct(
		public readonly string $description = '',
	) {}
}
