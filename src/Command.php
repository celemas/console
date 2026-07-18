<?php

declare(strict_types=1);

namespace Celemas\Cli;

use RuntimeException;

/**
 * @api
 */
abstract class Command
{
	public const int SUCCESS = 0;
	public const int FAILURE = 1;

	protected string $name = '';
	protected string $group = '';
	protected string $prefix = '';
	protected string $description = '';
	protected ?Output $output = null;

	abstract public function run(Args $args): int;

	public function name(): string
	{
		return $this->name;
	}

	public function group(): string
	{
		return $this->group;
	}

	public function prefix(): string
	{
		return $this->prefix === '' ? strtolower($this->group) : $this->prefix;
	}

	public function description(): string
	{
		return $this->description;
	}

	public function script(): string
	{
		return $_SERVER['argv'][0] ?? '';
	}

	public function output(Output $output): static
	{
		$this->output = $output;

		return $this;
	}

	public function echo(string $message, string $color = '', string $background = ''): void
	{
		$this->out()->echo($message, $color, $background);
	}

	public function echoln(string $message, string $color = '', string $background = ''): void
	{
		$this->out()->echoln($message, $color, $background);
	}

	public function info(string $message): void
	{
		$this->echoln($message);
	}

	public function success(string $message): void
	{
		$this->echoln($message, 'green');
	}

	public function warn(string $message): void
	{
		$this->out()->echolnErr($message, 'yellow');
	}

	public function error(string $message): void
	{
		$this->out()->echolnErr($message, 'red');
	}

	public function color(string $text, string $color, string $background = ''): string
	{
		return $this->out()->color($text, $color, $background);
	}

	public function indent(
		string $text,
		int $indent,
		?int $max = null,
	): string {
		return $this->out()->indent($text, $indent, $max);
	}

	private function out(): Output
	{
		return $this->output ?? throw new RuntimeException('Output missing');
	}

	public function help(): void
	{
		$this->helpHeader(withOptions: false);
	}

	protected function helpHeader(bool $withOptions = false): void
	{
		$script = $this->script();
		$name = $this->name;
		$prefix = $this->prefix();
		$desc = $this->description;

		if ($desc !== '') {
			$label = $this->color('Description:', 'brown') . "\n";
			$this->echo("{$label}  {$desc}\n\n");
		}

		$usage = $this->color('Usage:', 'brown') . "\n  php {$script} {$prefix}:{$name}";

		if ($withOptions) {
			$this->echo("{$usage} [options]\n\n");
			$this->echoln($this->color('Options:', 'brown'));

			return;
		}

		$this->echo("{$usage}\n");
	}

	/**
	 * Render one option in the help "Options:" block.
	 *
	 * Pass the flag names and, for value-taking options, a `value` label; the
	 * `--opt=<value>` notation is rendered here so it cannot drift from the
	 * `=`-only parser. Omit `value` for a boolean flag; set `optionalValue`
	 * for a flag whose value is optional (`--opt[=<value>]`).
	 */
	protected function helpOption(
		string $long,
		string $description,
		string $short = '',
		string $value = '',
		bool $optionalValue = false,
	): void {
		$suffix = match (true) {
			$value === '' => '',
			$optionalValue => "[=<{$value}>]",
			default => "=<{$value}>",
		};

		$option = $short === '' ? $long . $suffix : "{$short}{$suffix}, {$long}{$suffix}";

		$this->echo('    ' . $this->color($option, 'green') . "\n");
		$this->echo($this->indent($description, 8, 80) . "\n");
	}
}
