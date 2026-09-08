<?php

declare(strict_types=1);

/**
 * Verifies the logging wiring of a running application: which writer is bound,
 * whether the encryption path actually works, and whether the channel base path
 * is writable.
 *
 * This command exists because the writer's failure modes are silent by design —
 * a dropped secure record, a disabled writer, or an unwritable path produce no
 * runtime signal at all. Deploy scripts can gate on the exit code: 0 means the
 * wiring is healthy, 1 means logging is broken or off.
 *
 * The check reads the hydrated TraceLogConfig; an encryptor injected through a
 * DI factory override of WriterInterface is not visible to it.
 *
 * @internal
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\TraceLog\Cli;

use PHPdot\Console\Command;
use PHPdot\Contracts\Logs\WriterInterface;
use PHPdot\Logs\NullWriter;
use PHPdot\TraceLog\Encryption\ChaChaEncryptor;
use PHPdot\TraceLog\Exception\EncryptionException;
use PHPdot\TraceLog\TraceLogConfig;
use PHPdot\TraceLog\Writer\TraceLogWriter;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(name: 'tracelog:check', description: 'Verify the logging wiring: binding, encryption, and channel path.')]
final class CheckCommand extends Command
{
    /**
     * Reads configuration and touches the filesystem once — no pool, no client.
     */
    protected bool $coroutine = false;

    public function __construct(
        private readonly null|ContainerInterface $container = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('path', null, InputOption::VALUE_REQUIRED, 'Probe this base path instead of the configured one');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $container = $this->container;

        if ($container === null) {
            $this->error($output, 'tracelog:check inspects the running application container — run it through the application console (php dot tracelog:check).');

            return Command::FAILURE;
        }

        if (!$container->has(WriterInterface::class)) {
            $this->error($output, 'WriterInterface is not bound — the engine has nowhere to export.');

            return Command::FAILURE;
        }

        $resolved = $container->get(WriterInterface::class);

        if (!$resolved instanceof WriterInterface) {
            $this->error($output, 'The WriterInterface binding did not resolve to a writer.');

            return Command::FAILURE;
        }

        if ($resolved instanceof TraceLogWriter) {
            $override = $input->getOption('path');

            return $this->checkTraceLog(
                $output,
                $container,
                is_string($override) && $override !== '' ? $override : null,
            );
        }

        if ($resolved instanceof NullWriter) {
            $this->warning($output, 'NullWriter is bound — every engine record is discarded. Install a backend and rebuild the definitions.');

            return Command::FAILURE;
        }

        $this->info($output, sprintf('Backend: %s (no package-specific checks available).', $resolved::class));

        return Command::SUCCESS;
    }

    /**
     * Run the TraceLog-specific checks against the hydrated configuration.
     *
     * @param OutputInterface $output
     * @param ContainerInterface $container The application container.
     * @param string|null $overridePath Probe this path instead of the configured base path.
     *
     * @return int
     */
    private function checkTraceLog(OutputInterface $output, ContainerInterface $container, null|string $overridePath): int
    {
        try {
            $resolved = $container->get(TraceLogConfig::class);
        } catch (Throwable $error) {
            $this->error($output, 'config/tracelog.php could not be hydrated: ' . $error->getMessage());

            return Command::FAILURE;
        }

        if (!$resolved instanceof TraceLogConfig) {
            $this->error($output, 'The TraceLogConfig binding did not resolve to the config DTO.');

            return Command::FAILURE;
        }

        $config = $resolved;

        $problems = 0;

        $this->table($output, [
            ['setting' => 'Base path', 'value' => $config->basePath],
            ['setting' => 'Minimum level', 'value' => (string) $config->minLevel],
            ['setting' => 'Formatter', 'value' => $config->defaultFormatter],
            ['setting' => 'Max channels', 'value' => (string) $config->maxChannels],
            ['setting' => 'Enabled', 'value' => $config->enabled ? 'yes' : 'no'],
        ], ['Setting', 'Value']);

        if (!$config->enabled) {
            $this->warning($output, 'The writer is disabled — every record is discarded at the writer.');
            ++$problems;
        }

        $problems += $this->checkEncryption($output, $config->encryptionKey);
        $problems += $this->checkPath($output, $overridePath ?? $config->basePath);

        return $problems === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Prove the configured key decrypts what it encrypts — the exact operation
     * `->secure()` records depend on.
     *
     * @param OutputInterface $output
     * @param string|null $encryptionKey The configured key, or null when encryption is off.
     *
     * @return int The number of problems found (0 or 1).
     */
    private function checkEncryption(OutputInterface $output, null|string $encryptionKey): int
    {
        if ($encryptionKey === null) {
            $this->info($output, 'Encryption: disabled — ->secure() records are dropped fail-closed, never written plaintext.');

            return 0;
        }

        try {
            $encryptor = new ChaChaEncryptor($encryptionKey);

            $probe = 'tracelog:check ' . bin2hex(random_bytes(4));

            if ($encryptor->decrypt($encryptor->encrypt($probe)) !== $probe) {
                $this->error($output, 'Encryption: round-trip mismatch — the configured key is unusable.');

                return 1;
            }
        } catch (EncryptionException $error) {
            $this->error($output, 'Encryption: the configured key is invalid — ' . $error->getMessage());

            return 1;
        }

        $this->success($output, 'Encryption: round-trip ok.');

        return 0;
    }

    /**
     * Prove the channel base path can actually hold a file, creating it if needed.
     *
     * @param OutputInterface $output
     * @param string $path The configured (or overridden) base path.
     *
     * @return int The number of problems found (0 or 1).
     */
    private function checkPath(OutputInterface $output, string $path): int
    {
        if (!is_dir($path)) {
            @mkdir($path, 0o755, true);
        }

        $probe = $path . '/.tracelog-check-' . bin2hex(random_bytes(3));

        if (!@touch($probe)) {
            $this->error($output, sprintf('Path: not writable — %s.', is_dir($path) ? $path : "could not create {$path}"));

            return 1;
        }

        @unlink($probe);

        $this->success($output, "Path: writable ({$path}).");

        return 0;
    }
}
