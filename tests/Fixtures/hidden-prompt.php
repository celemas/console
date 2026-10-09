<?php

declare(strict_types=1);

/**
 * Child process for HiddenInputTest, run on a pseudo terminal.
 *
 * With the argument `tty` the prompt reads from the terminal device
 * itself while STDIN is something else; otherwise it reads STDIN.
 */

use Celema\Console\Io;
use Celema\Console\Stdio;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$target = ($argv[1] ?? '') === 'tty' ? (string) posix_ttyname(STDOUT) : 'php://stdin';
$io = new Io(new Stdio(input: $target));

try {
	$answer = $io->secret('Password:');
} catch (RuntimeException $e) {
	echo 'refused: ', $e->getMessage(), "\n";

	exit(1);
}

// Hex keeps the answer from matching an echoed copy in the transcript.
echo 'answer: ', bin2hex($answer), "\n";

// Stay alive until the test has checked the restored terminal state.
$io->ask('Done?');
