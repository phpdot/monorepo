<?php

declare(strict_types=1);

/**
 * Base class for SMTP server events with accept/reject pattern.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Gateway\Server\Event;

use PHPdot\Mail\Gateway\DataType\Enum\ReplyCode;
use PHPdot\Mail\Gateway\Exception\RuntimeException;

/**
 * Base class for all server-side SMTP events.
 *
 * Each event provides accept()/reject() methods for the handler to indicate the response.
 */
abstract class Event
{
    private bool|null $accepted = null;
    private mixed $result = null;
    private ReplyCode $rejectCode = ReplyCode::TransactionFailed;
    private string $rejectText = '';

    public function accept(mixed $data = null): void
    {
        if ($this->accepted !== null) {
            throw new RuntimeException('Event already handled');
        }
        $this->accepted = true;
        $this->result = $data;
    }

    public function reject(string $text, ReplyCode $code = ReplyCode::TransactionFailed): void
    {
        if ($this->accepted !== null) {
            throw new RuntimeException('Event already handled');
        }
        $this->accepted = false;
        $this->rejectCode = $code;
        $this->rejectText = $text;
    }

    public function isHandled(): bool
    {
        return $this->accepted !== null;
    }

    public function isAccepted(): bool
    {
        return $this->accepted === true;
    }

    public function getResult(): mixed
    {
        return $this->result;
    }

    public function getRejectCode(): ReplyCode
    {
        return $this->rejectCode;
    }

    public function getRejectText(): string
    {
        return $this->rejectText;
    }
}
