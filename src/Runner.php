<?php

declare(strict_types=1);

namespace Celema\Console;

use Celema\Console\Exception\InvalidUsage;
use Closure;
use Throwable;
use ValueError;

/**
 * @api
 */
final class Runner
{
	private const AMBIGUOUS = 1;

	/**
	 * The commands indexed by group and name.
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

	/** The widest listed name; the built-in `commands` sets the minimum. */
	private int $longestName = 8;

	/** @var null|Closure(class-string): object */
	private readonly ?Closure $resolve;

	/**
	 * An Io instance given as `$output` is used as is; `$errorOutput`
	 * then has no effect.
	 *
	 * @param null|callable(class-string): object $resolve
	 */
	public function __construct(
		array|object|string $commands = [],
		string|Io $output = 'php://stdout',
		string $errorOutput = 'php://stderr',
		private bool $debug = false,
		?callable $resolve = null,
	) {
		$this->io = is_string($output) ? new Io($output, $errorOutput) : $output;
		$this->resolve = $resolve === null ? null : Closure::fromCallable($resolve);
		$this->add($commands);
	}

	/**
	 * An added Commands collection keeps its own resolver.
	 */
	public function add(array|object|string $commands): self
	{
		// Index into copies so a rejected call registers nothing.
		$toc = $this->toc;
		$list = $this->list;
		$longestName = $this->longestName;

		foreach (new Commands($commands, $this->resolve)->entries() as $entry) {
			$meta = $entry->meta;

			if ($meta->prefix === '' && ($meta->name === 'help' || $meta->name === 'commands')) {
				throw new ValueError("Command name '{$meta->name}' is reserved");
			}

			$group = $toc[$meta->prefix] ?? ['title' => $meta->title(), 'commands' => []];

			if (array_key_exists($meta->name, $group['commands'])) {
				throw new ValueError("Duplicate command '{$meta->full()}'");
			}

			$group['commands'][$meta->name] = $entry;
			$toc[$meta->prefix] = $group;
			$list[$meta->name][] = $entry;
			$longestName = max($longestName, strlen($meta->full()));
		}

		$this->toc = $toc;
		$this->list = $list;
		$this->longestName = $longestName;

		return $this;
	}

	/**
	 * The script name in the usage line defaults to `$_SERVER['argv'][0]`.
	 */
	public function showHelp(?string $script = null): int
	{
		$script ??= $_SERVER['argv'][0] ?? '';
		$this->io->echo("<yellow>Usage:</yellow>\n");
		$this->io->echo("  php {$script} [prefix:]command [arguments]\n\n");
		$this->io->echo("Prefixes are optional if the command is unambiguous.\n\n");
		$this->io->echo("Available commands:\n");
		$this->echoGroup('General');
		$this->echoCommand('', 'commands', 'Lists all available commands');
		$this->echoCommand('', 'help', 'Displays this overview');

		// Render from the metadata, not the keys: PHP turns numeric
		// keys like '2026' into integers.
		$general = $this->toc['']['commands'] ?? [];
		ksort($general);

		foreach ($general as $entry) {
			$this->echoCommand('', $entry->meta->name, $entry->meta->description);
		}

		$toc = $this->toc;
		ksort($toc);

		foreach ($toc as $prefix => $group) {
			if ($prefix === '') {
				continue;
			}

			$this->echoGroup($group['title']);
			$commands = $group['commands'];
			ksort($commands);

			foreach ($commands as $entry) {
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

	/**
	 * Runs the command named in the argument vector, `$_SERVER['argv']` by
	 * default. A given vector has the same shape: it starts with the script
	 * name, which help screens and usage hints display.
	 *
	 * @param null|list<string> $argv
	 */
	public function run(?array $argv = null): int
	{
		$argv ??= $_SERVER['argv'] ?? [];
		$script = $argv[0] ?? '';

		try {
			$arg = $argv[1] ?? null;

			if ($arg === null) {
				return $this->showHelp($script);
			}

			$cmd = strtolower($arg);
			$isHelpCall = false;

			if ($cmd === 'help') {
				$isHelpCall = true;
				$arg = $argv[2] ?? null;

				if ($arg === null) {
					return $this->showHelp($script);
				}

				$cmd = strtolower($arg);
			}

			if ($cmd === 'commands') {
				return $this->showCommands();
			}

			$tokens = array_slice($argv, offset: 2);

			try {
				$entry = $this->getCommand($cmd);
			} catch (InvalidUsage $e) {
				if ($e->getCode() === self::AMBIGUOUS) {
					return $this->showAmbiguousMessage($cmd);
				}

				throw $e;
			}

			if ($isHelpCall) {
				return $this->showCommandHelp($entry, $script);
			}

			return $this->runCommand($entry, $tokens, $script);
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

			return $e instanceof InvalidUsage ? 2 : 1;
		}
	}

	/** @param list<string> $tokens */
	private function runCommand(Entry $entry, array $tokens, string $script): int
	{
		$signature = $entry->signature();
		$values = $signature->bind($tokens, $this->io, $script);

		// The signature checked that __invoke() exists; PHP requires it
		// to be public.
		/** @var callable-object $command */
		$command = $entry->command();

		/** @var int Guaranteed by the declared return type under strict_types */
		return $command(...$signature->match($values, $command));
	}

	private function showCommandHelp(Entry $entry, string $script): int
	{
		new Help($this->io, $script)->showFor($entry->class);

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

		return 2;
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

			return $this->toc[$parts[0]]['commands'][$parts[1]] ?? throw new InvalidUsage('Command not found');
		}

		if (array_key_exists($cmd, $this->toc['']['commands'] ?? [])) {
			return $this->toc['']['commands'][$cmd];
		}

		if (array_key_exists($cmd, $this->list)) {
			if (count($this->list[$cmd]) === 1) {
				return $this->list[$cmd][0];
			}

			throw new InvalidUsage('Ambiguous command', self::AMBIGUOUS);
		}

		throw new InvalidUsage('Command not found');
	}
}
