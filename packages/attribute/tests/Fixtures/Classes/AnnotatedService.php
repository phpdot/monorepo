<?php

declare(strict_types=1);

namespace PHPdot\Attribute\Tests\Fixtures\Classes;

use PHPdot\Attribute\Tests\Fixtures\Attributes\Column;
use PHPdot\Attribute\Tests\Fixtures\Attributes\Injectable;

#[Injectable(singleton: true)]
final class AnnotatedService
{
    #[Column('user_name')]
    public string $name = '';

    #[Column('user_email')]
    public string $email = '';

    public function process(): void {}
}
