<?php

declare(strict_types=1);

namespace Celema\Console;

use Throwable;
use ValueError;

/**
 * @api
 */
final class Runner
{
	private const AMBIGUOUS = 1;

	/**
	 * The commands ordered by group and name.
	 *
	 * @var array<string, array{title: string, commands: array<string, Entry>}>
	 */
	private array $toc = [];

	/**
	 * The commands indexed by name only.
	 *
	 * @var array<string, list<Entry>>
	 */
	private array $list = [];
	private Io $io;
	private int $longestName = 0;

	/**
	 * An Io instance given as `$output` is used as is; `$errorOutput`
	 * then has no effect.
	 */
	public function __construct(
		Commands $commands,
		string|Io $output = 'php://output',
		string $errorOutput = 'php://stderr',
		private bool $debug = false,
	) {
		$this->io = is_string($output) ? new Io($output, $errorOutput) : $output;
		$this->orderCommands($commands);
	}

	private function orderCommands(Commands $commands): void
	{
		$groups = [];

		foreach ($commands->entries() as $entry) {
			$meta = $entry->meta;

			if (!array_key_exists($meta->prefix, $groups)) {
				$groups[$meta->prefix] = [
					'title' => $meta->title(),
					'commands' => [],
				];
			}

			$groups[$meta->prefix]['commands'][$meta->name] = $entry;

			$this->list[$meta->name][] = $entry;

			$len = strlen($meta->full());
			$this->longestName = $len > $this->longestName ? $len : $this->longestName;
		}

		$this->longestName = max($this->longestName, strlen('commands'));

		ksort($groups);

		foreach ($groups as $name => $group) {
			$commands = $group['commands'];
			ksort($commands);
			$group['commands'] = $commands;
			$this->toc[$name] = $group;
		}
	}

	public function showHelp(): int
	{
		$script = $_SERVER['argv'][0] ?? '';
		$this->io->echo($this->io->color('Usage:', 'brown') . "\n");
		$this->io->echo("  php {$script} [prefix:]command [arguments]\n\n");
		$this->io->echo("Prefixes are optional if the command is unambiguous.\n\n");
		$this->io->echo("Available commands:\n");
		$this->echoGroup('General');
		$this->echoCommand('', 'commands', 'Lists all available commands');
		$this->echoCommand('', 'help', 'Displays this overview');

		foreach ($this->toc['']['commands'] ?? [] as $name => $entry) {
			$this->echoCommand('', $name, $entry->meta->description);
		}

		foreach ($this->toc as $prefix => $group) {
			if ($prefix === '') {
				continue;
			}

			$this->echoGroup($group['title']);

			foreach ($group['commands'] as $name => $entry) {
				$this->echoCommand($prefix, $name, $entry->meta->description);
			}
		}

		return 0;
	}

	/**
	 * Displays a list of all available commands.
	 *
	 * With and without namespace/group. If a command appears in more than
	 * one namespace, e. g. foo:cmd and bar:cmd, only the namespaced ones
	 * will be displayed.
	 */
	public function showCommands(): int
	{
		$list = [];

		foreach ($this->toc as $group) {
			foreach ($group['commands'] as $entry) {
				$meta = $entry->meta;

				if ($meta->prefix !== '') {
					$key = $meta->full();
					$list[$key] = ($list[$key] ?? 0) + 1;
				}

				$list[$meta->name] = ($list[$meta->name] ?? 0) + 1;
			}
		}

		ksort($list);

		foreach ($list as $name => $count) {
			if ($count === 1) {
				$this->io->echo("{$name}\n");
			}
		}

		return 0;
	}

	public function run(): int
	{
		try {
			$argv = $_SERVER['argv'] ?? [];
			$arg = $argv[1] ?? null;

			if ($arg === null) {
				return $this->showHelp();
			}

			$cmd = strtolower($arg);
			$isHelpCall = false;

			if ($cmd === 'help') {
				$isHelpCall = true;
				$arg = $argv[2] ?? null;

				if ($arg === null) {
					return $this->showHelp();
				}

				$cmd = strtolower($arg);
			}

			if ($cmd === 'commands') {
				return $this->showCommands();
			}

			$args = new Args(array_slice($argv, offset: 2));

			try {
				$entry = $this->getCommand($cmd);
			} catch (ValueError $e) {
				if ($e->getCode() === self::AMBIGUOUS) {
					return $this->showAmbiguousMessage($cmd);
				}

				throw $e;
			}

			if ($isHelpCall) {
				return $this->showCommandHelp($entry);
			}

			return $this->runCommand($entry, $args);
		} catch (Throwable $e) {
			$this->io->echoErr("Error while running command '");
			$this->io->echoErr($_SERVER['argv'][1] ?? '<no command given>');
			$this->io->echoErr("':\n\n" . $e->getMessage() . "\n");

			if ($this->debug) {
				$this->io->echolnErr("\nTraceback:", 'yellow');
				$this->io->echolnErr($e->getTraceAsString());
			}

			return 1;
		}
	}

	private function runCommand(Entry $entry, Args $args): int
	{
		$this->validate($entry, $args);
		$command = $entry->command();

		if (!is_callable($command)) {
			throw new ValueError("Command '{$entry->meta->full()}' is not callable");
		}

		$result = $command($args, $this->io);

		return is_int($result) ? $result : 0;
	}

	/**
	 * Checks the provided options against the command's declared `#[Opt]`s.
	 *
	 * A command declaring no options accepts anything; closures always do.
	 */
	private function validate(Entry $entry, Args $args): void
	{
		$opts = $entry->opts();

		if ($opts === []) {
			return;
		}

		$declared = [];

		foreach ($opts as $opt) {
			$declared[$opt->long] = $opt;

			if ($opt->short !== '') {
				$declared[$opt->short] = $opt;
			}
		}

		foreach ($args->names() as $name) {
			$opt = $declared[$name] ?? null;

			if ($opt === null) {
				throw new ValueError($this->unknownOption($name, $entry->meta->full(), array_keys($declared)));
			}

			$values = $args->opts($name);

			if ($opt->value === '' && $values !== []) {
				throw new ValueError("Option '{$name}' does not accept a value");
			}

			if ($opt->value !== '' && !$opt->optionalValue && $values === []) {
				throw new ValueError("Option '{$name}' requires a value: {$name}=<{$opt->value}>");
			}
		}
	}

	/** @param list<string> $declared */
	private function unknownOption(string $name, string $full, array $declared): string
	{
		if ($name === '--help' || $name === '-h') {
			$script = $_SERVER['argv'][0] ?? 'run';

			return "Unknown option '{$name}'. Use 'php {$script} help {$full}' to show the command's help";
		}

		$message = "Unknown option '{$name}'";
		$best = '';
		$bestDistance = PHP_INT_MAX;

		foreach ($declared as $candidate) {
			$distance = levenshtein($name, $candidate);

			if ($distance < $bestDistance) {
				$bestDistance = $distance;
				$best = $candidate;
			}
		}

		return $bestDistance <= 3 ? "{$message}. Did you mean '{$best}'?" : $message;
	}

	private function showCommandHelp(Entry $entry): int
	{
		new Help($this->io)->show($entry->meta, $entry->opts(), $entry->args());

		return 0;
	}

	private function echoGroup(string $title): void
	{
		$g = $this->io->color($title, 'brown');
		$this->io->echo("\n{$g}\n");
	}

	private function echoCommand(string $prefix, string $name, string $desc): void
	{
		$prefix = $prefix ? $prefix . ':' : '';
		$plain = $prefix . $name;
		$colored = $prefix . $this->io->color($name, 'green');

		// Pad on the visible length so columns align whether or not
		// color escapes are present.
		$pad = str_repeat(' ', max(2, $this->longestName + 2 - strlen($plain)));
		$this->io->echoln("  {$colored}{$pad}{$desc}");
	}

	private function showAmbiguousMessage(string $cmd): int
	{
		$this->io->echoErr("Ambiguous command. Please add the group name:\n\n");
		$entries = $this->list[$cmd];
		usort($entries, static fn(Entry $a, Entry $b): int => strcmp($a->meta->full(), $b->meta->full()));

		foreach ($entries as $entry) {
			$prefix = $this->io->color($entry->meta->prefix, 'brown');
			$this->io->echolnErr("  {$prefix}:{$entry->meta->name}");
		}

		return 1;
	}

	private function getCommand(string $cmd): Entry
	{
		if (array_key_exists($cmd, $this->list)) {
			if (count($this->list[$cmd]) === 1) {
				return $this->list[$cmd][0];
			}

			throw new ValueError('Ambiguous command', self::AMBIGUOUS);
		}

		if (str_contains($cmd, ':')) {
			/** @var array{0: string, 1: string} $parts */
			$parts = explode(':', $cmd, limit: 2);
			$group = $parts[0];
			$name = $parts[1];

			if (
				array_key_exists($group, $this->toc) && array_key_exists($name, $this->toc[$group]['commands'])
			) {
				return $this->toc[$group]['commands'][$name];
			}
		}

		throw new ValueError('Command not found');
	}
}
