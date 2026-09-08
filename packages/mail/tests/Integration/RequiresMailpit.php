<?php

declare(strict_types=1);

/**
 * Skips a test case when no Mailpit SMTP catcher is reachable. The probe is a
 * bare TCP connect to the SMTP port with a one-second budget, so an absent
 * server skips in well under a second instead of waiting on the transport's
 * own connect behaviour.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Tests\Integration;

trait RequiresMailpit
{
    protected function skipUnlessMailpitAvailable(): void
    {
        $socket = @fsockopen('tcp://' . self::mailpitHost(), self::mailpitSmtpPort(), $code, $reason, 1.0);
        if ($socket === false) {
            $this->markTestSkipped("Mailpit is not available: {$reason}");
        }
        fclose($socket);
    }

    /**
     * The DSN the delivery tests send through: Mailpit's SMTP endpoint as
     * published by the environment, defaulting to Mailpit's own port.
     *
     * @return string
     */
    protected static function mailpitSmtpDsn(): string
    {
        return 'smtp://' . self::mailpitHost() . ':' . self::mailpitSmtpPort();
    }

    /**
     * @return string
     */
    private static function mailpitHost(): string
    {
        return getenv('MAILPIT_HOST') ?: '127.0.0.1';
    }

    /**
     * @return int
     */
    private static function mailpitSmtpPort(): int
    {
        return (int) (getenv('MAILPIT_SMTP_PORT') ?: 1025);
    }
}
