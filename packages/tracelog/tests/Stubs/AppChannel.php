<?php

declare(strict_types=1);

namespace PHPdot\TraceLog\Tests\Stubs;

enum AppChannel: string
{
    case Payment = 'payment';
    case Webhook = 'webhook';
}
