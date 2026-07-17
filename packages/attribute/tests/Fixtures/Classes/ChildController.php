<?php

declare(strict_types=1);

namespace PHPdot\Attribute\Tests\Fixtures\Classes;

use PHPdot\Attribute\Tests\Fixtures\Attributes\Route;

#[Route('/admin/users')]
final class ChildController extends BaseController implements AnnotatedInterface
{
    #[Route('/admin/users', methods: ['GET'])]
    public function index(): void {}

    public function execute(): void {}
}
