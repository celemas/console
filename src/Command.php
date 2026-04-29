<?php

declare(strict_types=1);

namespace Duon\Cli;

use RuntimeException;

/**
 * @api
 */
abstract class Command
{
	protected string $name = '';
	protected string $group = '';
	protected string $prefix = '';
	protected string $description = '';
	protected ?Output $output = null;

	abstract public function run(): string|int;

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
		if ($this->output) {
			$this->output->echo($message, $color, $background);

			return;
		}

		throw new RuntimeException('Output missing');
	}

	public function echoln(string $message, string $color = '', string $background = ''): void
	{
		if ($this->output) {
			$this->output->echoln($message, $color, $background);

			return;
		}

		throw new RuntimeException('Output missing');
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
		$this->echoln($message, 'yellow');
	}

	public function error(string $message): void
	{
		$this->echoln($message, 'red');
	}

	public function color(string $text, string $color, string $background = ''): string
	{
		if ($this->output) {
			return $this->output->color($text, $color, $background);
		}

		throw new RuntimeException('Output missing');
	}

	public function indent(
		string $text,
		int $indent,
		?int $max = null,
	): string {
		if ($this->output) {
			return $this->output->indent($text, $indent, $max);
		}

		throw new RuntimeException('Output missing');
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
		} else {
			$this->echo("{$usage}\n");
		}
	}

	protected function helpOption(string $option, string $description): void
	{
		$this->echo('    ' . $this->color($option, 'green') . "\n");
		$this->echo($this->indent($description, 8, 80) . "\n");
	}
}
