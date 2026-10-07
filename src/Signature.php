<?php

declare(strict_types=1);

namespace Celema\Console;

use Celema\Console\Exception\InvalidUsage;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ValueError;

/**
 * The interface a command declares with its `__invoke()` parameters.
 *
 * Parameters typed `Args` or `Io` are injected; `#[Arg]` and `#[Opt]`
 * parameters receive the converted command-line input and form the
 * command's complete interface, together with the `#[Opt]` constructor
 * parameters of option groups. Declaration errors surface when the
 * signature is read, input errors when it is bound.
 *
 * @internal
 */
final class Signature
{
	/** @var list<Argument> */
	private array $arguments = [];

	/**
	 * Keyed by long name.
	 *
	 * @var array<string, Option>
	 */
	private array $options = [];

	/**
	 * The long names keyed by short name.
	 *
	 * @var array<string, string>
	 */
	private array $aliases = [];

	/**
	 * The names of the injected parameters, keyed by class.
	 *
	 * @var array<string, string>
	 */
	private array $injected = [];

	/**
	 * The option group classes, keyed by parameter name.
	 *
	 * @var array<string, class-string>
	 */
	private array $groups = [];

	/**
	 * The names of all `__invoke()` parameters, in declaration order.
	 *
	 * @var list<string>
	 */
	private array $parameters = [];

	private function __construct(
		private readonly string $full,
	) {}

	/**
	 * Reads the signature of a command class.
	 *
	 * @param class-string $class
	 * @param string $full The command's full name, for error messages
	 */
	public static function of(string $class, string $full): self
	{
		$command = new ReflectionClass($class);

		if (!$command->hasMethod('__invoke')) {
			throw new ValueError("Command '{$full}' is not callable");
		}

		$method = $command->getMethod('__invoke');
		$return = $method->getReturnType();

		if (!$return instanceof ReflectionNamedType || $return->getName() !== 'int' || $return->allowsNull()) {
			throw new ValueError("Command '{$full}' must declare the return type int");
		}

		$signature = new self($full);

		foreach ($method->getParameters() as $parameter) {
			$signature->add($parameter);
		}

		$signature->checkArguments();

		return $signature;
	}

	/**
	 * Converts a parameter name to the kebab-case of command-line names.
	 */
	public static function kebab(string $name): string
	{
		return strtolower(preg_replace('/(?<=[a-z0-9])[A-Z]/', '-$0', $name) ?? $name);
	}

	/** @return list<Argument> */
	public function arguments(): array
	{
		return $this->arguments;
	}

	/** @return array<string, Option> Keyed by long name */
	public function options(): array
	{
		return $this->options;
	}

	/**
	 * Validates the command-line tokens against the declarations and
	 * converts them to the named arguments of `__invoke()`.
	 *
	 * @throws InvalidUsage
	 *
	 * Absent options and optional arguments are left out, so the
	 * parameters' defaults apply.
	 *
	 * `$script` names the runner script in the `--help` hint.
	 *
	 * @param list<string> $tokens
	 * @return array<string, mixed>
	 */
	public function bind(array $tokens, Io $io, string $script): array
	{
		[$args, $counts] = $this->parse($tokens);
		$values = [];

		foreach ($this->injected as $class => $name) {
			$values[$name] = $class === Args::class ? $args : $io;
		}

		$grouped = [];

		foreach ($args->names() as $name) {
			$option = $this->options[$name] ?? throw new InvalidUsage($this->unknownOption($name, $script));

			if ($option->group === null) {
				$values[$option->parameter->name] = $option->value($args, $counts[$name]);
			} else {
				$grouped[$option->group][$option->parameter->name] = $option->value($args, $counts[$name]);
			}
		}

		foreach ($this->groups as $name => $class) {
			$values[$name] = new $class(...$grouped[$name] ?? []);
		}

		return [...$values, ...$this->bindArguments($args->positionals())];
	}

	/**
	 * Renames the bound values to the parameters of the command's own
	 * `__invoke()`, matching them by position.
	 *
	 * The values are bound to the registered class, but a factory may
	 * return a subclass whose override renames the parameters.
	 *
	 * @param array<string, mixed> $values
	 * @return array<string, mixed>
	 */
	public function match(array $values, object $command): array
	{
		$parameters = new ReflectionMethod($command, '__invoke')->getParameters();
		$matched = [];

		foreach ($this->parameters as $position => $name) {
			if (array_key_exists($name, $values)) {
				$matched[$parameters[$position]->name] = $values[$name];
			}
		}

		return $matched;
	}

	private function add(ReflectionParameter $parameter): void
	{
		$this->parameters[] = $parameter->name;
		$arg = $parameter->getAttributes(Arg::class)[0] ?? null;
		$opt = $parameter->getAttributes(Opt::class)[0] ?? null;

		if ($arg !== null && $opt !== null) {
			throw new ValueError(
				"Command '{$this->full}' parameter \${$parameter->name} cannot carry both #[Arg] and #[Opt]",
			);
		}

		if ($arg !== null) {
			$this->addArgument(new Argument($parameter, $arg->newInstance(), $this->type($parameter)));

			return;
		}

		if ($opt !== null) {
			$this->addOption(new Option($parameter, $opt->newInstance(), $this->type($parameter)));

			return;
		}

		$this->inject($parameter);
	}

	private function type(ReflectionParameter $parameter): Type
	{
		if ($parameter->isVariadic()) {
			throw new ValueError(
				"Command '{$this->full}' parameter \${$parameter->name} cannot be variadic; declare it as array",
			);
		}

		return (
			Type::of($parameter)
				?? throw new ValueError(
					"Command '{$this->full}' parameter \${$parameter->name} must be declared as "
						. 'string, int, float, bool, array, or a backed enum',
				)
		);
	}

	private function addArgument(Argument $argument): void
	{
		if ($argument->type->name === 'bool') {
			throw new ValueError(
				"Command '{$this->full}' argument '<{$argument->name}>' cannot be a bool; declare a flag with #[Opt]",
			);
		}

		$this->arguments[] = $argument;
	}

	private function addOption(Option $option): void
	{
		$this->checkOption($option);

		foreach ([$option->name, $option->opt->short] as $name) {
			if (array_key_exists($name, $this->options) || array_key_exists($name, $this->aliases)) {
				throw new ValueError("Command '{$this->full}' declares the option name '{$name}' twice");
			}
		}

		$this->options[$option->name] = $option;

		if ($option->opt->short !== '') {
			$this->aliases[$option->opt->short] = $option->name;
		}
	}

	private function checkOption(Option $option): void
	{
		$parameter = $option->parameter;
		$prefix = "Command '{$this->full}'";

		// PHP also drops the default of a parameter declared before a
		// required one.
		if (!$parameter->isDefaultValueAvailable()) {
			throw new ValueError("{$prefix} option '{$option->name}' needs a default value");
		}

		$bare = $option->opt->bare;

		if ($option->type->name === 'bool') {
			if ($parameter->getDefaultValue() !== false) {
				throw new ValueError("{$prefix} flag '{$option->name}' must default to false");
			}

			if ($option->opt->value !== '' || $bare !== null) {
				throw new ValueError("{$prefix} flag '{$option->name}' takes no value");
			}
		} elseif ($bare !== null) {
			if ($option->type->name === 'array') {
				throw new ValueError("{$prefix} repeatable option '{$option->name}' cannot have a bare value");
			}

			if ($option->type->convert($bare) === null) {
				throw new ValueError(
					"{$prefix} option '{$option->name}' needs {$option->type->expected()} as bare value, got '{$bare}'",
				);
			}
		}
	}

	private function inject(ReflectionParameter $parameter): void
	{
		$type = $parameter->getType();
		$class = $type instanceof ReflectionNamedType && !$type->allowsNull() && !$parameter->isVariadic()
			? $type->getName()
			: '';

		if ($class !== Args::class && $class !== Io::class) {
			$this->addGroup($parameter, $class);
		} elseif (array_key_exists($class, $this->injected)) {
			$short = $class === Args::class ? 'Args' : 'Io';

			throw new ValueError("Command '{$this->full}' declares more than one {$short} parameter");
		} else {
			$this->injected[$class] = $parameter->name;
		}
	}

	/**
	 * Adds the options of an option group: a class whose constructor
	 * parameters all carry `#[Opt]`.
	 */
	private function addGroup(ReflectionParameter $parameter, string $class): void
	{
		$constructor = class_exists($class) ? new ReflectionClass($class)->getConstructor() : null;
		$members = $constructor?->getParameters() ?? [];

		if (!array_any(
			$members,
			static fn(ReflectionParameter $member): bool => $member->getAttributes(Opt::class) !== [],
		)) {
			throw new ValueError(
				"Command '{$this->full}' parameter \${$parameter->name} must be declared as Args, Io, "
					. 'or an option group, or carry #[Arg] or #[Opt]',
			);
		}

		foreach ($members as $member) {
			$opt = $member->getAttributes(Opt::class)[0] ?? throw new ValueError(
				"Command '{$this->full}' option group {$class} parameter \${$member->name} must carry #[Opt]",
			);

			$this->addOption(new Option($member, $opt->newInstance(), $this->type($member), $parameter->name));
		}

		/** @var class-string $class Checked by class_exists() */
		$this->groups[$parameter->name] = $class;
	}

	private function checkArguments(): void
	{
		$last = count($this->arguments) - 1;

		foreach ($this->arguments as $index => $argument) {
			if ($argument->variadic() && $index < $last) {
				throw new ValueError(
					"Command '{$this->full}' declares an argument after the variadic '<{$argument->name}>'",
				);
			}
		}
	}

	/**
	 * Parses the tokens, replacing declared short names with their long
	 * names.
	 *
	 * Also counts how often each name occurs, since Args merges the
	 * occurrences: two bare `--worker` must not pass for one value.
	 * Positionals are counted too, but only option names are looked up.
	 *
	 * @param list<string> $tokens
	 * @return array{Args, array<string, int>}
	 */
	private function parse(array $tokens): array
	{
		$normalized = [];
		$counts = [];
		$literal = false;

		foreach ($tokens as $token) {
			// Aliasing stops at the `--` separator; Args reads every
			// later token as a positional. The token is command-line
			// input, not a secret.
			// @mago-expect lint:no-insecure-comparison
			if ($literal || $token === '--') {
				$literal = true;
				$normalized[] = $token;

				continue;
			}

			$separator = strpos(haystack: $token, needle: '=');
			$name = $separator === false
				? $token
				: substr(string: $token, offset: 0, length: $separator);
			$long = $this->aliases[$name] ?? null;
			$key = $long ?? $name;
			$counts[$key] = ($counts[$key] ?? 0) + 1;
			$normalized[] = $long === null
				? $token
				: $long . ($separator === false ? '' : substr(string: $token, offset: $separator));
		}

		return [new Args($normalized), $counts];
	}

	/**
	 * @param list<string> $positionals
	 * @return array<string, mixed>
	 */
	private function bindArguments(array $positionals): array
	{
		$required = count(array_filter(
			$this->arguments,
			static fn(Argument $argument): bool => !$argument->optional(),
		));
		$count = count($positionals);

		// PHP makes every parameter before a required one required, so the
		// required arguments always lead.
		if ($count < $required) {
			throw new InvalidUsage("Missing required argument '<{$this->arguments[$count]->name}>'");
		}

		$values = [];

		foreach ($this->arguments as $index => $argument) {
			if ($argument->variadic()) {
				$rest = array_slice($positionals, $index);

				if ($rest !== []) {
					$values[$argument->parameter->name] = $rest;
				}

				return $values;
			}

			if ($index < $count) {
				$values[$argument->parameter->name] = $argument->convert($positionals[$index]);
			}
		}

		$surplus = $positionals[count($this->arguments)] ?? null;

		if ($surplus !== null) {
			throw new InvalidUsage("Unexpected argument '{$surplus}'");
		}

		return $values;
	}

	private function unknownOption(string $name, string $script): string
	{
		if ($name === '--help' || $name === '-h') {
			return "Unknown option '{$name}'. Use 'php {$script} help {$this->full}' to show the command's help";
		}

		$message = "Unknown option '{$name}'";
		$best = '';
		$bestDistance = PHP_INT_MAX;

		foreach ([...array_keys($this->options), ...array_keys($this->aliases)] as $candidate) {
			$distance = levenshtein($name, $candidate);

			if ($distance < $bestDistance) {
				$bestDistance = $distance;
				$best = $candidate;
			}
		}

		return $bestDistance <= 3 ? "{$message}. Did you mean '{$best}'?" : $message;
	}
}
