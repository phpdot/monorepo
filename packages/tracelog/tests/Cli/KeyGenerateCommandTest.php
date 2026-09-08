<?php

declare(strict_types=1);

/**
 * Key Generate Command Test
 *
 * Pins the stdout contract: the one line the command prints is a key the
 * encryptor accepts verbatim.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\TraceLog\Tests\Cli;

use PHPdot\TraceLog\Cli\KeyGenerateCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class KeyGenerateCommandTest extends TestCase
{
    private string $output = '';

    #[Test]
    public function printsABareKeyTheEncryptorAcceptsVerbatim(): void
    {
        $exit = $this->runCommand([]);

        self::assertSame(Command::SUCCESS, $exit);

        $line = trim($this->output);
        self::assertMatchesRegularExpression('/^[A-Za-z0-9+\/]{43}=$/', $line, 'exactly one bare base64 line, pipeable');
        self::assertSame(32, strlen((string) base64_decode($line, true)));
    }

    private function runCommand(array $input): int
    {
        $command = new KeyGenerateCommand();
        $buffer = new BufferedOutput();

        $exit = $command->run(new ArrayInput($input), $buffer);

        $this->output = $buffer->fetch();

        return $exit;
    }
}
