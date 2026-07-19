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

	// The commands ordered by group and name
	private array $toc = [];

	// The commands indexed by name only
	private array $list = [];
	private Output $output;
	private int $longestName = 0;

	public function __construct(
		Commands $commands,
		string $output = 'php://output',
		string $errorOutput = 'php://stderr',
		private bool $debug = false,
	) {
		$this->output = new Output($output, $errorOutput);
		$this->orderCommands($commands);
	}

	private function orderCommands(Commands $commands): void
	{
		$groups = [];

		foreach ($commands->get() as $command) {
			$name = strtolower($command->name());
			$prefix = $command->prefix();

			if (!array_key_exists($prefix, $groups)) {
				$group = $command->group();
				$group = $group === '' ? 'General' : $group;
				$groups[$prefix] = [
					'title' => $prefix === '' ? 'General' : $group,
					'commands' => [],
				];
			}

			$groups[$prefix]['commands'][$name] = $command;

			$this->list[$name][] = $command;

			$len = strlen($prefix . ':' . $command->name());
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
		$this->output->echo($this->output->color('Usage:', 'brown') . "\n");
		$this->output->echo("  php {$script} [prefix:]command [arguments]\n\n");
		$this->output->echo("Prefixes are optional if the command is unambiguous.\n\n");
		$this->output->echo("Available commands:\n");
		$this->echoGroup('General');
		$this->echoCommand('', 'commands', 'Lists all available commands');
		$this->echoCommand('', 'help', 'Displays this overview');

		foreach ($this->toc['']['commands'] ?? [] as $name => $command) {
			$this->echoCommand($command->prefix(), $name, $command->description());
		}

		foreach ($this->toc as $prefix => $group) {
			if ($prefix === '') {
				continue;
			}

			$this->echoGroup($group['title']);

			foreach ($group['commands'] as $name => $command) {
				$this->echoCommand($command->prefix(), $name, $command->description());
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
			foreach ($group['commands'] as $command) {
				$prefix = $command->prefix();

				if ($prefix) {
					$key = "{$prefix}:" . $command->name();
					$list[$key] = ($list[$key] ?? 0) + 1;
				}

				$name = $command->name();
				$list[$name] = ($list[$name] ?? 0) + 1;
			}
		}

		ksort($list);

		foreach ($list as $name => $count) {
			if ($count === 1) {
				$this->output->echo("{$name}\n");
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
				$command = $this->getCommand($cmd)->output($this->output);

				if ($isHelpCall) {
					$command->help();

					return 0;
				}

				return $command->run($args);
			} catch (ValueError $e) {
				if ($e->getCode() === self::AMBIGUOUS) {
					return $this->showAmbiguousMessage($cmd);
				}

				throw $e;
			}
		} catch (Throwable $e) {
			$this->output->echoErr("Error while running command '");
			$this->output->echoErr($_SERVER['argv'][1] ?? '<no command given>');
			$this->output->echoErr("':\n\n" . $e->getMessage() . "\n");

			if ($this->debug) {
				$this->output->echolnErr("\nTraceback:", 'yellow');
				$this->output->echolnErr($e->getTraceAsString());
			}

			return 1;
		}
	}

	private function echoGroup(string $title): void
	{
		$g = $this->output->color($title, 'brown');
		$this->output->echo("\n{$g}\n");
	}

	private function echoCommand(string $prefix, string $name, string $desc): void
	{
		$prefix = $prefix ? $prefix . ':' : '';
		$plain = $prefix . $name;
		$colored = $prefix . $this->output->color($name, 'green');

		// Pad on the visible length so columns align whether or not
		// color escapes are present.
		$pad = str_repeat(' ', max(2, $this->longestName + 2 - strlen($plain)));
		$this->output->echoln("  {$colored}{$pad}{$desc}");
	}

	private function showAmbiguousMessage(string $cmd): int
	{
		$this->output->echoErr("Ambiguous command. Please add the group name:\n\n");
		asort($this->list[$cmd]);

		foreach ($this->list[$cmd] as $command) {
			$prefix = $this->output->color($command->prefix(), 'brown');
			$name = strtolower($command->name());
			$this->output->echolnErr("  {$prefix}:{$name}");
		}

		return 1;
	}

	private function getCommand(string $cmd): Command
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
