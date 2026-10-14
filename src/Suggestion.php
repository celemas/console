<?php

declare(strict_types=1);

namespace Celema\Console;

/**
 * Finds the name a mistyped one was meant to be, for "Did you mean"
 * hints.
 *
 * @internal
 */
final class Suggestion
{
	/**
	 * The candidate nearest to the typed name, if it is near enough: one
	 * typo per three characters, at least one. A single character has to
	 * match: every other one is just a typo away.
	 *
	 * @param list<string> $candidates The first one wins a tie
	 * @param string $ignore Leading characters that do not count, like
	 *     the dashes of options
	 */
	public static function find(string $typed, array $candidates, string $ignore = ''): ?string
	{
		$typed = ltrim($typed, $ignore);
		$length = strlen($typed);
		$best = '';
		$bestDistance = PHP_INT_MAX;

		foreach ($candidates as $candidate) {
			$distance = self::distance($typed, ltrim($candidate, $ignore));

			if ($distance < $bestDistance) {
				$bestDistance = $distance;
				$best = $candidate;
			}
		}

		$limit = $length > 1 ? max(1, intdiv($length, 3)) : 0;

		return $bestDistance <= $limit ? $best : null;
	}

	/**
	 * The number of typos between two names. The beginning of a name and
	 * a swapped pair of neighbours count as one, where levenshtein() sees
	 * more.
	 */
	private static function distance(string $typed, string $name): int
	{
		$distance = levenshtein($typed, $name);

		// The XOR is zero up to the first difference.
		$at = strspn($typed ^ $name, "\0");
		$swapped = substr_replace($typed, strrev(substr($typed, $at, 2)), $at, 2);

		return $swapped === $name || str_starts_with($name, $typed) ? min($distance, 1) : $distance;
	}
}
