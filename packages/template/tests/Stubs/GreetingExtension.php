<?php

declare(strict_types=1);

namespace PHPdot\Template\Tests\Stubs;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class GreetingExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('greet', static fn(string $name): string => "hello, {$name}"),
        ];
    }
}
