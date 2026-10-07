<?php

declare(strict_types=1);

namespace Celema\Console\Exception;

use RuntimeException;

/**
 * Reports a command invoked the wrong way, like an unknown option or a
 * missing argument.
 *
 * The runner prints the message and exits with code 2, which tells a
 * wrong invocation apart from a failed run. Commands throw it for checks
 * of their own, such as options that cannot be combined.
 *
 * @api
 */
final class InvalidUsage extends RuntimeException {}
