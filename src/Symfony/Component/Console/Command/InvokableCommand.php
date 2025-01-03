<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Command;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Represents an invokable command.
 *
 * @author Yonel Ceruto <open@yceruto.dev>
 *
 * @internal
 */
class InvokableCommand
{
    public function __construct(
        private readonly Command $command,
        private readonly \Closure $code,
    ) {
    }

    /**
     * Invokes a callable with parameters generated from the input interface.
     */
    public function __invoke(InputInterface $input, OutputInterface $output): mixed
    {
        return ($this->code)(...$this->parameters($input, $output));
    }

    /**
     * Configures the input definition from an invokable-defined function.
     *
     * Processes the parameters of the reflection function to extract and
     * add arguments or options to the provided input definition.
     */
    public function configure(InputDefinition $definition): void
    {
        $reflection = new \ReflectionFunction($this->code);

        foreach ($reflection->getParameters() as $parameter) {
            if ($argument = Argument::tryFrom($parameter)) {
                $definition->addArgument($argument->toInputArgument());
            } elseif ($option = Option::tryFrom($parameter)) {
                $definition->addOption($option->toInputOption());
            }
        }
    }

    private function parameters(InputInterface $input, OutputInterface $output): array
    {
        $parameters = [];
        $reflection = new \ReflectionFunction($this->code);

        foreach ($reflection->getParameters() as $parameter) {
            if ($argument = Argument::tryFrom($parameter)) {
                $parameters[] = $argument->resolveValue($input);

                continue;
            }

            if ($option = Option::tryFrom($parameter)) {
                $parameters[] = $option->resolveValue($input);

                continue;
            }

            $type = $parameter->getType();

            if (!$type instanceof \ReflectionNamedType) {
                continue;
            }

            $parameters[] = match ($type->getName()) {
                InputInterface::class => $input,
                OutputInterface::class => $output,
                SymfonyStyle::class => new SymfonyStyle($input, $output),
                Application::class => $this->command->getApplication(),
                default => throw new RuntimeException(\sprintf('Unsupported type "%s" for parameter "$%s".', $type->getName(), $parameter->getName())),
            };
        }

        return $parameters ?: [$input, $output];
    }
}
