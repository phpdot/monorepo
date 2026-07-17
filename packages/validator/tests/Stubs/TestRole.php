<?php

declare(strict_types=1);

namespace PHPdot\Validator\Tests\Stubs;

enum TestRole: string
{
    case Admin = 'admin';
    case Editor = 'editor';
    case Viewer = 'viewer';
}
