<?php

declare(strict_types=1);

namespace PHPdot\Attribute\Tests\Fixtures\Classes;

use PHPdot\Attribute\Tests\Fixtures\Attributes\Injectable;

#[Injectable]
interface AnnotatedInterface
{
    public function execute(): void;
}
