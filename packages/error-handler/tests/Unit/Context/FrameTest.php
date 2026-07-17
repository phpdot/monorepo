<?php

declare(strict_types=1);

namespace PHPdot\ErrorHandler\Tests\Unit\Context;

use PHPdot\ErrorHandler\Context\CodeLine;
use PHPdot\ErrorHandler\Context\Frame;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FrameTest extends TestCase
{
    #[Test]
    public function storesFile(): void
    {
        $frame = $this->makeFrame(file: '/app/src/Foo.php');

        self::assertSame('/app/src/Foo.php', $frame->file);
    }

    #[Test]
    public function storesLine(): void
    {
        $frame = $this->makeFrame(line: 42);

        self::assertSame(42, $frame->line);
    }

    #[Test]
    public function storesClassName(): void
    {
        $frame = $this->makeFrame(class: 'App\\Controller\\HomeController');

        self::assertSame('App\\Controller\\HomeController', $frame->class);
    }

    #[Test]
    public function storesNullClass(): void
    {
        $frame = $this->makeFrame(class: null);

        self::assertNull($frame->class);
    }

    #[Test]
    public function storesFunctionName(): void
    {
        $frame = $this->makeFrame(function: 'index');

        self::assertSame('index', $frame->function);
    }

    #[Test]
    public function storesNullFunction(): void
    {
        $frame = $this->makeFrame(function: null);

        self::assertNull($frame->function);
    }

    #[Test]
    public function storesCodeSnippet(): void
    {
        $snippet = [
            new CodeLine(lineNumber: 1, code: '<?php', isHighlighted: false),
            new CodeLine(lineNumber: 2, code: '$x = 1;', isHighlighted: true),
        ];
        $frame = $this->makeFrame(codeSnippet: $snippet);

        self::assertCount(2, $frame->codeSnippet);
        self::assertSame('<?php', $frame->codeSnippet[0]->code);
    }

    #[Test]
    public function storesEmptyCodeSnippet(): void
    {
        $frame = $this->makeFrame(codeSnippet: []);

        self::assertSame([], $frame->codeSnippet);
    }

    #[Test]
    public function storesIsApplicationTrue(): void
    {
        $frame = $this->makeFrame(isApplication: true);

        self::assertTrue($frame->isApplication);
    }

    #[Test]
    public function storesIsApplicationFalse(): void
    {
        $frame = $this->makeFrame(isApplication: false);

        self::assertFalse($frame->isApplication);
    }

    #[Test]
    public function isReadonly(): void
    {
        $frame = $this->makeFrame();
        $ref = new \ReflectionClass($frame);

        self::assertTrue($ref->isReadOnly());
    }

    #[Test]
    public function isFinal(): void
    {
        $ref = new \ReflectionClass(Frame::class);

        self::assertTrue($ref->isFinal());
    }

    #[Test]
    public function storesFileWithSpaces(): void
    {
        $frame = $this->makeFrame(file: '/app/My Project/src/Foo.php');

        self::assertSame('/app/My Project/src/Foo.php', $frame->file);
    }

    #[Test]
    public function storesZeroLine(): void
    {
        $frame = $this->makeFrame(line: 0);

        self::assertSame(0, $frame->line);
    }

    /**
     * @param list<CodeLine> $codeSnippet
     */
    private function makeFrame(
        string $file = '/app/src/Foo.php',
        int $line = 10,
        ?string $class = null,
        ?string $function = null,
        array $codeSnippet = [],
        bool $isApplication = true,
    ): Frame {
        return new Frame(
            file: $file,
            line: $line,
            class: $class,
            function: $function,
            codeSnippet: $codeSnippet,
            isApplication: $isApplication,
        );
    }
}
