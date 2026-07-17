<?php

declare(strict_types=1);

namespace PHPdot\Attribute\Tests\Fixtures\Classes;

use PHPdot\Attribute\Tests\Fixtures\Attributes\Broken;
use PHPdot\Attribute\Tests\Fixtures\Attributes\Route;

#[Broken]
#[Route('/broken')]
final class BrokenAttributeFixture
{
    #[Route('/works')]
    public function works(): void {}
}
