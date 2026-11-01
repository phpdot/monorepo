<?php

declare(strict_types=1);

/**
 * SMTP reply codes per RFC 5321.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Gateway\DataType\Enum;

/**
 * RFC 5321 Section 4.2: Reply codes.
 */
enum ReplyCode: int
{
    case SystemStatus = 211;
    case HelpMessage = 214;
    case ServiceReady = 220;
    case ServiceClosing = 221;
    case AuthSuccess = 235;
    case Ok = 250;
    case UserNotLocal = 251;

    case AuthContinue = 334;
    case StartMailInput = 354;

    case ServiceNotAvailable = 421;
    case MailboxBusy = 450;
    case LocalError = 451;
    case InsufficientStorage = 452;
    case TempAuthFailure = 454;

    case SyntaxError = 500;
    case SyntaxParamError = 501;
    case NotImplemented = 502;
    case BadSequence = 503;
    case ParamNotImplemented = 504;
    case AuthRequired = 530;
    case AuthFailed = 535;
    case MailboxNotFound = 550;
    case UserNotLocalError = 551;
    case ExceededStorage = 552;
    case MailboxNameNotAllowed = 553;
    case TransactionFailed = 554;

    public function isSuccess(): bool
    {
        return $this->value < 300;
    }

    public function isIntermediate(): bool
    {
        return $this->value >= 300 && $this->value < 400;
    }

    public function isTransientFailure(): bool
    {
        return $this->value >= 400 && $this->value < 500;
    }

    public function isPermanentFailure(): bool
    {
        return $this->value >= 500;
    }
}
