<?php

declare(strict_types=1);

/**
 * Generates a base64 ChaCha20-Poly1305 key for `->secure()` log records.
 *
 * The bare key is the only stdout line, so it pipelines:
 * `php dot tracelog:key:generate | pbcopy`. Any framing text would break that
 * and any other format would be rejected by the encryptor's constructor.
 *
 * @internal
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\TraceLog\Cli;

use PHPdot\Console\Command;
use PHPdot\TraceLog\Encryption\ChaChaEncryptor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'tracelog:key:generate', description: 'Generate a key for encrypted log records.')]
final class KeyGenerateCommand extends Command
{
    /**
     * Touches only randomness — no pool, no client, no filesystem.
     */
    protected bool $coroutine = false;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(ChaChaEncryptor::generateKey());

        return Command::SUCCESS;
    }
}
