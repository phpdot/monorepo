<?php

declare(strict_types=1);

namespace PHPdot\ErrorHandler\Tests\Unit\Context;

use PHPdot\ErrorHandler\Context\CodeLine;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CodeLineTest extends TestCase
{
    #[Test]
    public function storesLineNumber(): void
    {
        $line = new CodeLine(lineNumber: 42, code: 'echo "hello";', isHighlighted: false);

        self::assertSame(42, $line->lineNumber);
    }

    #[Test]
    public function storesCode(): void
    {
        $line = new CodeLine(lineNumber: 1, code: '$x = 1;', isHighlighted: false);

        self::assertSame('$x = 1;', $line->code);
    }

    #[Test]
    public function storesHighlightedTrue(): void
    {
        $line = new CodeLine(lineNumber: 1, code: '', isHighlighted: true);

        self::assertTrue($line->isHighlighted);
    }

    #[Test]
    public function storesHighlightedFalse(): void
    {
        $line = new CodeLine(lineNumber: 1, code: '', isHighlighted: false);

        self::assertFalse($line->isHighlighted);
    }

    #[Test]
    public function storesEmptyCode(): void
    {
        $line = new CodeLine(lineNumber: 1, code: '', isHighlighted: false);

        self::assertSame('', $line->code);
    }

    #[Test]
    public function storesCodeWithSpecialCharacters(): void
    {
        $code = '<?php echo "<html>" & $var;';
        $line = new CodeLine(lineNumber: 10, code: $code, isHighlighted: true);

        self::assertSame($code, $line->code);
    }

    #[Test]
    public function storesZeroLineNumber(): void
    {
        $line = new CodeLine(lineNumber: 0, code: '', isHighlighted: false);

        self::assertSame(0, $line->lineNumber);
    }

    #[Test]
    public function storesLargeLineNumber(): void
    {
        $line = new CodeLine(lineNumber: 999999, code: '', isHighlighted: false);

        self::assertSame(999999, $line->lineNumber);
    }

    #[Test]
    public function isReadonly(): void
    {
        $line = new CodeLine(lineNumber: 1, code: 'test', isHighlighted: false);
        $ref = new \ReflectionClass($line);

        self::assertTrue($ref->isReadOnly());
    }

    #[Test]
    public function isFinal(): void
    {
        $ref = new \ReflectionClass(CodeLine::class);

        self::assertTrue($ref->isFinal());
    }

    #[Test]
    public function storesMultibyteCode(): void
    {
        $code = '$name = "Cafe\u{0301}";'; // unicode
        $line = new CodeLine(lineNumber: 5, code: $code, isHighlighted: false);

        self::assertSame($code, $line->code);
    }

    #[Test]
    public function storesCodeWithTabs(): void
    {
        $code = "\t\t\$x = 1;";
        $line = new CodeLine(lineNumber: 3, code: $code, isHighlighted: true);

        self::assertSame($code, $line->code);
    }
}
