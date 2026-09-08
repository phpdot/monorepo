<?php

declare(strict_types=1);

namespace PHPdot\Package\Tests\Fixtures;

enum StringMode: string
{
    case Eof = 'eof';
    case Length = 'length';
}
