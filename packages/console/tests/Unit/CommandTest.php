<?php

declare(strict_types=1);

namespace PHPdot\Console\Tests\Unit;

use PHPdot\Console\Tests\Fixtures\GreetCommand;
use PHPdot\Console\Tests\Fixtures\MathAddCommand;
use PHPdot\Console\Tests\Fixtures\ProgressCommand;
use PHPdot\Console\Tests\Fixtures\TableCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class CommandTest extends TestCase
{
    #[Test]
    public function infoOutputsInfoTag(): void
    {
        $tester = new CommandTester(new GreetCommand());
        $tester->execute([]);

        self::assertStringContainsString('Hello, World!', $tester->getDisplay());
    }

    #[Test]
    public function infoOutputsWithCustomName(): void
    {
        $tester = new CommandTester(new GreetCommand());
        $tester->execute(['name' => 'Omar']);

        self::assertStringContainsString('Hello, Omar!', $tester->getDisplay());
    }

    #[Test]
    public function errorOutputsErrorTag(): void
    {
        $command = new class extends \PHPdot\Console\Command {
            protected function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): int
            {
                $this->error($output, 'Something went wrong');

                return self::SUCCESS;
            }
        };

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertStringContainsString('Something went wrong', $tester->getDisplay());
    }

    #[Test]
    public function successOutputsCheckMark(): void
    {
        $command = new class extends \PHPdot\Console\Command {
            protected function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): int
            {
                $this->success($output, 'Done');

                return self::SUCCESS;
            }
        };

        $tester = new CommandTester($command);
        $tester->execute([]);

        $display = $tester->getDisplay();
        self::assertStringContainsString('✔', $display);
        self::assertStringContainsString('Done', $display);
    }

    #[Test]
    public function warningOutputsWarningPrefix(): void
    {
        $command = new class extends \PHPdot\Console\Command {
            protected function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): int
            {
                $this->warning($output, 'Be careful');

                return self::SUCCESS;
            }
        };

        $tester = new CommandTester($command);
        $tester->execute([]);

        $display = $tester->getDisplay();
        self::assertStringContainsString('⚠', $display);
        self::assertStringContainsString('Be careful', $display);
    }

    #[Test]
    public function commentOutputsCommentTag(): void
    {
        $command = new class extends \PHPdot\Console\Command {
            protected function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): int
            {
                $this->comment($output, 'Just a note');

                return self::SUCCESS;
            }
        };

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertStringContainsString('Just a note', $tester->getDisplay());
    }

    #[Test]
    public function tableRendersWithAutoDetectedHeaders(): void
    {
        $tester = new CommandTester(new TableCommand());
        $tester->execute([]);

        $display = $tester->getDisplay();
        self::assertStringContainsString('name', $display);
        self::assertStringContainsString('age', $display);
        self::assertStringContainsString('Alice', $display);
        self::assertStringContainsString('30', $display);
        self::assertStringContainsString('Bob', $display);
        self::assertStringContainsString('25', $display);
    }

    #[Test]
    public function tableRendersWithExplicitHeaders(): void
    {
        $command = new class extends \PHPdot\Console\Command {
            protected function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): int
            {
                $this->table($output, [
                    ['name' => 'Alice', 'age' => '30'],
                    ['name' => 'Bob', 'age' => '25'],
                ], ['Name', 'Age']);

                return self::SUCCESS;
            }
        };

        $tester = new CommandTester($command);
        $tester->execute([]);

        $display = $tester->getDisplay();
        self::assertStringContainsString('Name', $display);
        self::assertStringContainsString('Age', $display);
        self::assertStringContainsString('Alice', $display);
        self::assertStringContainsString('Bob', $display);
    }

    #[Test]
    public function withProgressIteratesAllItems(): void
    {
        $tester = new CommandTester(new ProgressCommand());
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
    }

    #[Test]
    public function withProgressCallsCallbackForEachItem(): void
    {
        /** @var list<string> $processed */
        $processed = [];

        $command = new class ($processed) extends \PHPdot\Console\Command {
            /** @param list<string> $processed */
            public function __construct(
                private array &$processed,
            ) {
                parent::__construct();
            }

            protected function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): int
            {
                $this->withProgress($output, ['x', 'y', 'z'], function (string $item, int $index): void {
                    $this->processed[] = $item;
                });

                return self::SUCCESS;
            }
        };

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(['x', 'y', 'z'], $processed);
    }

    #[Test]
    public function mathAddCommandOutputsSum(): void
    {
        $tester = new CommandTester(new MathAddCommand());
        $tester->execute(['a' => '3', 'b' => '7']);

        self::assertStringContainsString('10', $tester->getDisplay());
        self::assertSame(0, $tester->getStatusCode());
    }

    #[Test]
    public function greetCommandReturnsSuccess(): void
    {
        $tester = new CommandTester(new GreetCommand());
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
    }

    #[Test]
    public function withProgressAcceptsExplicitTotalForGenerators(): void
    {
        /** @var list<int> $processed */
        $processed = [];

        $command = new class ($processed) extends \PHPdot\Console\Command {
            /** @param list<int> $processed */
            public function __construct(
                private array &$processed,
            ) {
                parent::__construct();
            }

            protected function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): int
            {
                $generator = (function (): \Generator {
                    yield 1;
                    yield 2;
                    yield 3;
                })();

                $this->withProgress($output, $generator, function (int $item, int $index): void {
                    $this->processed[] = $item;
                }, total: 3);

                return self::SUCCESS;
            }
        };

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame([1, 2, 3], $processed);
        self::assertSame(0, $tester->getStatusCode());
    }
}
