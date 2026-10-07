<?php

declare(strict_types=1);

namespace Celema\Console;

use Attribute;
use ValueError;

/**
 * Declares an `__invoke()` parameter as an option.
 *
 * The option takes its name from the parameter, converted to kebab-case:
 * `$dryRun` becomes `--dry-run`. Every option needs a default, which the
 * command receives when the option is absent and which the help renders
 * as `[default: ...]`. The declared type decides the form:
 *
 * - `bool`: a flag without value, such as `--force`; it must default to
 *   `false`.
 * - `string`, `int`, `float`, or a backed enum: one `--name=<value>`,
 *   converted to the type.
 * - `array`: repeatable, `--tag=a --tag=b`, collecting the values as
 *   strings.
 *
 * `short` adds an alias like `-p`. `value` overrides the `<value>` label,
 * which defaults to the option name. `bare` makes the value optional: a
 * bare `--name` then stands for `--name=<bare>`, and the help renders
 * `--name[=<value>]`.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class Opt
{
	public function __construct(
		public readonly string $description = '',
		public readonly string $short = '',
		public readonly string $value = '',
		public readonly ?string $bare = null,
	) {
		if ($short !== '' && preg_match('/^-[^-=\s][^=\s]*$/', $short) !== 1) {
			throw new ValueError("Invalid short option name '{$short}'");
		}
	}
}
