<?php

declare(strict_types=1);

namespace PHPdot\Attribute\Tests\Fixtures\Classes;

use PHPdot\Attribute\Tests\Fixtures\Attributes\Middleware;
use PHPdot\Attribute\Tests\Fixtures\Attributes\Route;
use PHPdot\Attribute\Tests\Fixtures\Attributes\Validated;

#[Route('/users')]
#[Middleware('auth')]
final class AnnotatedController
{
    #[Route('/users', methods: ['GET'], name: 'users.index')]
    public function index(): void {}

    #[Route('/users/{id}', methods: ['GET'], name: 'users.show')]
    public function show(int $id): void {}

    #[Route('/users', methods: ['POST'], name: 'users.store')]
    public function store(#[Validated] array $data): void {}
}
