<?php

declare(strict_types=1);

namespace PHPdot\ErrorHandler\Tests\Unit\Context;

use PHPdot\ErrorHandler\Context\StackTrace;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StackTraceTest extends TestCase
{
    #[Test]
    public function fromExceptionBuildsFrames(): void
    {
        $exception = new \RuntimeException('test error');
        $trace = StackTrace::fromException($exception);

        self::assertNotEmpty($trace->frames);
    }

    #[Test]
    public function firstFrameIsExceptionOrigin(): void
    {
        $exception = new \RuntimeException('test error');
        $line = __LINE__ - 1;
        $trace = StackTrace::fromException($exception);

        self::assertSame(__FILE__, $trace->frames[0]->file);
        self::assertSame($line, $trace->frames[0]->line);
    }

    #[Test]
    public function firstFrameHasNullClassAndFunction(): void
    {
        $exception = new \RuntimeException('test');
        $trace = StackTrace::fromException($exception);

        self::assertNull($trace->frames[0]->class);
        self::assertNull($trace->frames[0]->function);
    }

    #[Test]
    public function subsequentFramesHaveTraceInfo(): void
    {
        $exception = $this->createNestedError();
        $trace = StackTrace::fromException($exception);

        // Frame 0 is the exception origin, frame 1+ are from the trace
        self::assertGreaterThan(1, count($trace->frames));
    }

    #[Test]
    public function codeSnippetContainsCorrectLines(): void
    {
        $exception = new \RuntimeException('test');
        $errorLine = __LINE__ - 1;
        $trace = StackTrace::fromException($exception, contextLines: 3);

        $snippet = $trace->frames[0]->codeSnippet;
        self::assertNotEmpty($snippet);

        // The error line should be in the snippet
        $highlightedLines = array_filter($snippet, static fn ($l) => $l->isHighlighted);
        self::assertCount(1, $highlightedLines);

        $highlighted = array_values($highlightedLines)[0];
        self::assertSame($errorLine, $highlighted->lineNumber);
    }

    #[Test]
    public function onlyOneLineIsHighlighted(): void
    {
        $exception = new \RuntimeException('test');
        $trace = StackTrace::fromException($exception, contextLines: 9);

        $snippet = $trace->frames[0]->codeSnippet;
        $highlightedCount = count(array_filter($snippet, static fn ($l) => $l->isHighlighted));

        self::assertSame(1, $highlightedCount);
    }

    #[Test]
    public function contextLinesControlsSnippetSize(): void
    {
        $exception = new \RuntimeException('test');
        $traceBig = StackTrace::fromException($exception, contextLines: 5);
        $traceSmall = StackTrace::fromException($exception, contextLines: 1);

        self::assertGreaterThan(
            count($traceSmall->frames[0]->codeSnippet),
            count($traceBig->frames[0]->codeSnippet),
        );
    }

    #[Test]
    public function handlesNonexistentFile(): void
    {
        // Create an exception that references a file that doesn't exist
        $exception = new \ErrorException('test', 0, E_ERROR, '/nonexistent/file.php', 10);
        $trace = StackTrace::fromException($exception);

        // First frame should have empty snippet since file doesn't exist
        self::assertSame([], $trace->frames[0]->codeSnippet);
    }

    #[Test]
    public function handlesUnknownFileInTrace(): void
    {
        $exception = new \RuntimeException('test');
        $trace = StackTrace::fromException($exception);

        // Should not throw, even if trace contains unknown files
        self::assertInstanceOf(StackTrace::class, $trace);
    }

    #[Test]
    public function applicationFrameDetection(): void
    {
        $exception = new \RuntimeException('test');
        $trace = StackTrace::fromException($exception);

        // The first frame is this test file, which is not in vendor
        self::assertTrue($trace->frames[0]->isApplication);
    }

    #[Test]
    public function vendorFrameDetection(): void
    {
        // Create an exception that looks like it came from vendor
        $vendorPath = '/app/vendor/some/package/src/Foo.php';
        $exception = new \ErrorException('test', 0, E_ERROR, $vendorPath, 5);
        $trace = StackTrace::fromException($exception);

        self::assertFalse($trace->frames[0]->isApplication);
    }

    #[Test]
    public function snippetDoesNotGoBeforeFileStart(): void
    {
        // Create a temp file with few lines
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        self::assertNotFalse($tmpFile);
        file_put_contents($tmpFile, "<?php\n\$x = 1;\n\$y = 2;\n");

        try {
            $exception = new \ErrorException('test', 0, E_ERROR, $tmpFile, 1);
            $trace = StackTrace::fromException($exception, contextLines: 20);

            $snippet = $trace->frames[0]->codeSnippet;
            self::assertNotEmpty($snippet);
            self::assertSame(1, $snippet[0]->lineNumber);
        } finally {
            unlink($tmpFile);
        }
    }

    #[Test]
    public function snippetDoesNotGoBeyondFileEnd(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        self::assertNotFalse($tmpFile);
        file_put_contents($tmpFile, "line1\nline2\nline3\n");

        try {
            $exception = new \ErrorException('test', 0, E_ERROR, $tmpFile, 3);
            $trace = StackTrace::fromException($exception, contextLines: 20);

            $snippet = $trace->frames[0]->codeSnippet;
            $lastLine = end($snippet);
            self::assertNotFalse($lastLine);
            self::assertSame(3, $lastLine->lineNumber);
        } finally {
            unlink($tmpFile);
        }
    }

    #[Test]
    public function codeLineContentIsCorrect(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        self::assertNotFalse($tmpFile);
        file_put_contents($tmpFile, "<?php\n\$x = 1;\n\$y = 2;\n");

        try {
            $exception = new \ErrorException('test', 0, E_ERROR, $tmpFile, 2);
            $trace = StackTrace::fromException($exception, contextLines: 1);

            $snippet = $trace->frames[0]->codeSnippet;
            $found = false;
            foreach ($snippet as $line) {
                if ($line->lineNumber === 2) {
                    self::assertSame('$x = 1;', $line->code);
                    self::assertTrue($line->isHighlighted);
                    $found = true;
                }
            }
            self::assertTrue($found, 'Expected line 2 in snippet');
        } finally {
            unlink($tmpFile);
        }
    }

    #[Test]
    public function codeSnippetStripsTrailingNewlines(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        self::assertNotFalse($tmpFile);
        file_put_contents($tmpFile, "line1\r\nline2\nline3\r\n");

        try {
            $exception = new \ErrorException('test', 0, E_ERROR, $tmpFile, 1);
            $trace = StackTrace::fromException($exception, contextLines: 5);

            foreach ($trace->frames[0]->codeSnippet as $line) {
                self::assertStringNotContainsString("\n", $line->code);
                self::assertStringNotContainsString("\r", $line->code);
            }
        } finally {
            unlink($tmpFile);
        }
    }

    #[Test]
    public function framesPropertyIsReadonly(): void
    {
        $ref = new \ReflectionClass(StackTrace::class);

        self::assertTrue($ref->isReadOnly());
    }

    #[Test]
    public function constructorAcceptsEmptyFrames(): void
    {
        $trace = new StackTrace(frames: []);

        self::assertSame([], $trace->frames);
    }

    #[Test]
    public function multipleFramesFromDeepCallStack(): void
    {
        $exception = $this->level3();
        $trace = StackTrace::fromException($exception);

        // At minimum: exception origin + level3 + level2 + level1 + this test
        self::assertGreaterThanOrEqual(4, count($trace->frames));
    }

    #[Test]
    public function defaultContextLinesIsNine(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        self::assertNotFalse($tmpFile);
        // Create a file with 30 lines
        $content = implode("\n", array_map(static fn ($i) => "line $i", range(1, 30)));
        file_put_contents($tmpFile, $content);

        try {
            $exception = new \ErrorException('test', 0, E_ERROR, $tmpFile, 15);
            $trace = StackTrace::fromException($exception); // default contextLines=9

            $snippet = $trace->frames[0]->codeSnippet;
            // Should have 9 lines before + 1 highlighted + 9 lines after = 19
            self::assertSame(19, count($snippet));
        } finally {
            unlink($tmpFile);
        }
    }

    private function createNestedError(): \RuntimeException
    {
        return new \RuntimeException('nested error');
    }

    private function level1(): \RuntimeException
    {
        return $this->level2();
    }

    private function level2(): \RuntimeException
    {
        return $this->level3();
    }

    private function level3(): \RuntimeException
    {
        return new \RuntimeException('deep error');
    }
}
