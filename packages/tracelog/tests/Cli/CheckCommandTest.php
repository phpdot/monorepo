<?php

declare(strict_types=1);

/**
 * Check Command Test
 *
 * Pins the failure modes that are silent in production: a disabled writer, an
 * unusable key, an unwritable channel path, and the NullWriter default. The
 * exit code is the contract — deploy scripts gate on it.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\TraceLog\Tests\Cli;

use PHPdot\Contracts\Logs\WriterInterface;
use PHPdot\Logs\NullWriter;
use PHPdot\TraceLog\Cli\CheckCommand;
use PHPdot\TraceLog\Encryption\ChaChaEncryptor;
use PHPdot\TraceLog\TraceLogConfig;
use PHPdot\TraceLog\Writer\TraceLogWriter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class CheckCommandTest extends TestCase
{
    private string $output = '';

    #[Test]
    public function aHealthyWiringExitsZero(): void
    {
        $dir = sys_get_temp_dir() . '/tracelog_check_' . bin2hex(random_bytes(4));

        $exit = $this->runCommand($this->container($this->config($dir, ChaChaEncryptor::generateKey())));

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('round-trip ok', $this->output);
        self::assertStringContainsString('writable', $this->output);
    }

    #[Test]
    public function aDisabledWriterFailsTheCheck(): void
    {
        $dir = sys_get_temp_dir() . '/tracelog_check_' . bin2hex(random_bytes(4));

        $exit = $this->runCommand($this->container($this->config($dir, null, enabled: false)));

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('disabled', $this->output);
    }

    #[Test]
    public function anInvalidKeyFailsTheCheck(): void
    {
        $dir = sys_get_temp_dir() . '/tracelog_check_' . bin2hex(random_bytes(4));

        $container = $this->container(null);
        $container->services[TraceLogConfig::class] = $this->config($dir, 'not-a-base64-256-bit-key');

        $exit = $this->runCommand($container);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('key is invalid', $this->output);
    }

    #[Test]
    public function anUnwritableBasePathFailsTheCheck(): void
    {
        $blocked = sys_get_temp_dir() . '/tracelog_check_file_' . bin2hex(random_bytes(4));
        file_put_contents($blocked, 'a file where a directory was wanted');

        $exit = $this->runCommand($this->container($this->config($blocked, null)));

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('not writable', $this->output);
    }

    #[Test]
    public function aNullWriterBindingFailsTheCheck(): void
    {
        $container = $this->container(null);
        $container->services[WriterInterface::class] = new NullWriter();

        $exit = $this->runCommand($container);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('NullWriter', $this->output);
    }

    #[Test]
    public function aPathOverrideBypassesTheConfiguredPath(): void
    {
        $blocked = sys_get_temp_dir() . '/tracelog_check_file_' . bin2hex(random_bytes(4));
        file_put_contents($blocked, 'a file where a directory was wanted');

        $good = sys_get_temp_dir() . '/tracelog_check_' . bin2hex(random_bytes(4));

        $exit = $this->runCommand($this->container($this->config($blocked, null)), ['--path' => $good]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString($good, $this->output);
    }

    #[Test]
    public function withoutAContainerItRefusesToGuess(): void
    {
        $buffer = new BufferedOutput();

        $exit = (new CheckCommand())->run(new ArrayInput([]), $buffer);
        $this->output = $buffer->fetch();

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('application container', $this->output);
    }

    private function config(string $basePath, null|string $key, bool $enabled = true): TraceLogConfig
    {
        return new TraceLogConfig(basePath: $basePath, encryptionKey: $key, enabled: $enabled);
    }

    /**
     * A minimal PSR-11 stand-in carrying exactly the services the check resolves.
     *
     * @param TraceLogConfig|null $config When null, a healthy default is bound.
     *
     * @return object&ContainerInterface
     */
    private function container(null|TraceLogConfig $config): ContainerInterface
    {
        $config = $config ?? new TraceLogConfig(basePath: sys_get_temp_dir() . '/tracelog_check_default_' . bin2hex(random_bytes(4)));

        $container = new class ($config) implements ContainerInterface {
            /** @var array<string, mixed> */
            public array $services = [];

            public function __construct(TraceLogConfig $config)
            {
                $this->services[TraceLogConfig::class] = $config;
                $this->services[WriterInterface::class] = new TraceLogWriter($config);
            }

            public function get(string $id): mixed
            {
                return $this->services[$id]
                    ?? throw new \RuntimeException("unknown service {$id}");
            }

            public function has(string $id): bool
            {
                return array_key_exists($id, $this->services);
            }
        };

        return $container;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function runCommand(ContainerInterface $container, array $input = []): int
    {
        $buffer = new BufferedOutput();

        $exit = (new CheckCommand($container))->run(new ArrayInput($input), $buffer);

        $this->output = $buffer->fetch();

        return $exit;
    }
}
