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
		string|Io $output = 'php://stdout',
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

			if ($meta->prefix === '' && ($meta->name === 'help' || $meta->name === 'commands')) {
				throw new ValueError("Command name '{$meta->name}' is reserved");
			}

			if (!array_key_exists($meta->prefix, $groups)) {
				$groups[$meta->prefix] = [
					'title' => $meta->title(),
					'commands' => [],
				];
			}

			if (array_key_exists($meta->name, $groups[$meta->prefix]['commands'])) {
				throw new ValueError("Duplicate command '{$meta->full()}'");
			}

			$groups[$meta->prefix]['commands'][$meta->name] = $entry;
			$this->list[$meta->name][] = $entry;

			$this->longestName = max($this->longestName, strlen($meta->full()));
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
		$this->io->echo("<yellow>Usage:</yellow>\n");
		$this->io->echo("  php {$script} [prefix:]command [arguments]\n\n");
		$this->io->echo("Prefixes are optional if the command is unambiguous.\n\n");
		$this->io->echo("Available commands:\n");
		$this->echoGroup('General');
		$this->echoCommand('', 'commands', 'Lists all available commands');
		$this->echoCommand('', 'help', 'Displays this overview');

		// Render from the metadata, not the keys: PHP turns numeric
		// keys like '2026' into integers.
		foreach ($this->toc['']['commands'] ?? [] as $entry) {
			$this->echoCommand('', $entry->meta->name, $entry->meta->description);
		}

		foreach ($this->toc as $prefix => $group) {
			if ($prefix === '') {
				continue;
			}

			$this->echoGroup($group['title']);

			foreach ($group['commands'] as $entry) {
				$this->echoCommand($entry->meta->prefix, $entry->meta->name, $entry->meta->description);
			}
		}

		return 0;
	}

	/**
	 * Displays a list of all available commands.
	 *
	 * With and without namespace/group. If a bare name is shared, e. g.
	 * by foo:cmd and bar:cmd, only the namespaced forms are displayed —
	 * unless the bare name belongs to an unprefixed command, which always
	 * shows since it resolves exactly.
	 */
	public function showCommands(): int
	{
		$list = [];

		foreach ($this->toc as $group) {
			foreach ($group['commands'] as $entry) {
				$meta = $entry->meta;

				// Full names are unique; only bare names can be shared.
				if ($meta->prefix !== '') {
					$list[$meta->full()] = 1;
				}

				$list[$meta->name] = ($list[$meta->name] ?? 0) + 1;
			}
		}

		ksort($list);

		foreach ($list as $name => $count) {
			if ($count === 1 || array_key_exists($name, $this->toc['']['commands'] ?? [])) {
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

			$tokens = array_slice($argv, offset: 2);

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

			return $this->runCommand($entry, $tokens);
		} catch (Throwable $e) {
			// Escape the arbitrary strings: a message containing markup
			// (or broken markup) must never throw while reporting. `$arg`
			// names the effective target, e.g. `x` for `help x`.
			$this->io->echoErr("Error while running command '");
			$this->io->echoErr($this->io->escape($arg ?? '<no command given>'));
			$this->io->echoErr("':\n\n" . $this->io->escape($e->getMessage()) . "\n");

			if ($this->debug) {
				$this->io->echolnErr("\n<yellow>Traceback:</yellow>");
				$this->io->echolnErr($this->io->escape($e->getTraceAsString()));
			}

			return 1;
		}
	}

	/** @param list<string> $tokens */
	private function runCommand(Entry $entry, array $tokens): int
	{
		$values = $entry->signature()->bind($tokens, $this->io);

		// The signature checked that __invoke() exists; PHP requires it
		// to be public.
		/** @var callable $command */
		$command = $entry->command();

		/** @var int Guaranteed by the declared return type under strict_types */
		return $command(...$values);
	}

	private function showCommandHelp(Entry $entry): int
	{
		new Help($this->io)->showFor($entry->class);

		return 0;
	}

	private function echoGroup(string $title): void
	{
		$this->io->echo("\n<yellow>{$title}</yellow>\n");
	}

	private function echoCommand(string $prefix, string $name, string $desc): void
	{
		$prefix = $prefix === '' ? '' : $prefix . ':';

		// Pad on the visible length; the markup tags don't print. The
		// longest name includes every listed one, so the gap is at least 2.
		$pad = str_repeat(' ', $this->longestName + 2 - strlen($prefix . $name));
		$this->io->echoln("  {$prefix}<green>{$name}</green>{$pad}{$desc}");
	}

	private function showAmbiguousMessage(string $cmd): int
	{
		$this->io->echoErr("Ambiguous command. Please add the group name:\n\n");
		$entries = $this->list[$cmd];
		usort($entries, static fn(Entry $a, Entry $b): int => strcmp($a->meta->full(), $b->meta->full()));

		foreach ($entries as $entry) {
			$this->io->echolnErr("  <yellow>{$entry->meta->prefix}</yellow>:{$entry->meta->name}");
		}

		return 1;
	}

	private function getCommand(string $cmd): Entry
	{
		// Exact full names resolve first: an unprefixed command wins over
		// its prefixed namesakes, and prefixed lookups are always exact.
		// Only then a bare name serves as the alias of a unique prefixed
		// command; shared by several, it is ambiguous.
		if (str_contains($cmd, ':')) {
			/** @var array{0: string, 1: string} $parts */
			$parts = explode(':', $cmd, limit: 2);

			return $this->toc[$parts[0]]['commands'][$parts[1]] ?? throw new ValueError('Command not found');
		}

		if (array_key_exists($cmd, $this->toc['']['commands'] ?? [])) {
			return $this->toc['']['commands'][$cmd];
		}

		if (array_key_exists($cmd, $this->list)) {
			if (count($this->list[$cmd]) === 1) {
				return $this->list[$cmd][0];
			}

			throw new ValueError('Ambiguous command', self::AMBIGUOUS);
		}

		throw new ValueError('Command not found');
	}
}
