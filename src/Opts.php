<?php

declare(strict_types=1);

namespace Duon\Cli;

use PhpParser\NodeVisitor\FirstFindingVisitor;
use ValueError;

/**
 * PHP's native `getopt` stops after the first "non-option" argument
 * which in our case is the command in `php run <command>`.
 *
 * `-arg`, `--arg` and even `---arg` are recognized but treated as different flags.
 *
 * @api
 */
final class Opts
{
	private readonly array $opts;

	public function __construct()
	{
		$this->opts = $this->getOpts();
	}

	public function has(string $key, bool $default = false): bool
	{
		if (isset($this->opts[$key])) {
			return true;
		}

		return $default;
	}

	public function get(string $key, string $default = ''): string
	{
		if (func_num_args() === 1) {
			$this->validate($key);

			return $this->opts[$key]->get();
		}

		if (!$this->has($key)) {
			return $default;
		}

		if ($this->opts[$key]->isset()) {
			return $this->opts[$key]->get();
		}

		return $default;
	}

	public function all(string $key, array $default = []): array
	{
		if (func_num_args() === 1) {
			$this->validate($key);

			return $this->opts[$key]->all();
		}

		if (!$this->has($key)) {
			return $default;
		}

		if ($this->opts[$key]->isset()) {
			return $this->opts[$key]->all();
		}

		return $default;
	}

	private static function getOpts(): array
	{
		$opts = [];
		$key = null;

		foreach ($_SERVER['argv'] ?? [] as $arg) {
			if (str_starts_with($arg, '-')) {
				$key = $arg;
				$value = null;

				if (str_contains($key, '=')) {
					$parts = explode('=', $key);
					$key = array_shift($parts);
					$value = implode('=', $parts);
				}

				if (isset($opts[$key])) {
					if ($value !== null) {
						$opts[$key]->set($value);
					}
				} else {
					$opts[$key] = new Opt($value);
				}
			} else {
				if ($key !== null) {
					$opts[$key]->set($arg);
				}
			}
		}

		return $opts;
	}

	private function validate(string $key): void
	{
		if (!isset($this->opts[$key])) {
			throw new ValueError("Unknown option: {$key}");
		}

		if (isset($this->opts[$key]) && !$this->opts[$key]->isset()) {
			throw new ValueError("No value given for {$key}");
		}
	}
}
