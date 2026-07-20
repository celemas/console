<?php

declare(strict_types=1);

namespace Celema\Console;

/**
 * Parses a command's argument tokens into options and positionals.
 *
 * Options use `--key=value` and repeat: `--tag=a --tag=b`. A dashed token
 * without `=`, such as `--force` or `-h`, is a boolean flag. Every other
 * token is a positional. Unlike PHP's native `getopt`, this reads an
 * explicit token list, so the command name never interferes with parsing.
 *
 * A positional cannot start with `-`; such a token is read as a flag —
 * unless it follows a `--` separator: the first `--` ends option parsing
 * and every later token is a positional, dashed or not.
 *
 * @api
 */
final class Args
{
	/** @var array<string, list<string>> */
	private array $options = [];

	/** @var array<string, true> */
	private array $bare = [];

	/** @var list<string> */
	private array $positionals = [];

	/**
	 * @param list<string> $tokens
	 */
	public function __construct(array $tokens = [])
	{
		$literal = false;

		foreach ($tokens as $token) {
			if ($literal || !str_starts_with($token, '-')) {
				$this->positionals[] = $token;

				continue;
			}

			// The token is command-line input, not a secret.
			// @mago-expect lint:no-insecure-comparison
			if ($token === '--') {
				$literal = true;

				continue;
			}

			if (str_contains($token, '=')) {
				/** @var array{0: string, 1: string} $pair */
				$pair = explode('=', $token, limit: 2);
				$this->options[$pair[0]][] = $pair[1];

				continue;
			}

			$this->options[$token] ??= [];
			$this->bare[$token] = true;
		}
	}

	/**
	 * Whether the option occurred at least once without a value.
	 */
	public function bare(string $key): bool
	{
		return array_key_exists($key, $this->bare);
	}

	public function has(string $key): bool
	{
		return array_key_exists($key, $this->options);
	}

	/**
	 * The names of all provided options, in first-seen order.
	 *
	 * @return list<string>
	 */
	public function names(): array
	{
		return array_keys($this->options);
	}

	public function opt(string $key, string $default = ''): string
	{
		return $this->options[$key][0] ?? $default;
	}

	/**
	 * @param list<string> $default
	 * @return list<string>
	 */
	public function opts(string $key, array $default = []): array
	{
		$values = $this->options[$key] ?? [];

		return $values === [] ? $default : $values;
	}

	public function positional(int $index, ?string $default = null): ?string
	{
		return $this->positionals[$index] ?? $default;
	}

	/**
	 * @return list<string>
	 */
	public function positionals(): array
	{
		return $this->positionals;
	}
}
