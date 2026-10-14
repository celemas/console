<?php

declare(strict_types=1);

/**
 * Child process for StdioTerminalTest, run on a pseudo terminal: prints
 * colored text and the detected width.
 */

use Celema\Console\Io;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$io = new Io();
$io->line('<green>colored</green> %d', $io->width());
