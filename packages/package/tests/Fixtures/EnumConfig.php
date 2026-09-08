<?php

declare(strict_types=1);

namespace PHPdot\Package\Tests\Fixtures;

use PHPdot\Container\Attribute\Config;

#[Config('enum-sample')]
final readonly class EnumConfig
{
    public function __construct(
        public StringMode $mode = StringMode::Eof,
        public IntLevel $level = IntLevel::High,
        public PureState $state = PureState::Active,
        public string $name = 'x',
    ) {}
}
