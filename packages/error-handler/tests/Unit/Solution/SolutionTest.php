<?php

declare(strict_types=1);

namespace PHPdot\ErrorHandler\Tests\Unit\Solution;

use PHPdot\ErrorHandler\Solution\Solution;
use PHPdot\ErrorHandler\Solution\SolutionLink;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SolutionTest extends TestCase
{
    #[Test]
    public function storesTitle(): void
    {
        $solution = new Solution(title: 'Class not found', description: 'Run composer install');

        self::assertSame('Class not found', $solution->title);
    }

    #[Test]
    public function storesDescription(): void
    {
        $solution = new Solution(title: 'Fix', description: 'Run composer dump-autoload');

        self::assertSame('Run composer dump-autoload', $solution->description);
    }

    #[Test]
    public function storesLinks(): void
    {
        $links = [
            new SolutionLink(label: 'Docs', url: 'https://example.com/docs'),
            new SolutionLink(label: 'FAQ', url: 'https://example.com/faq'),
        ];
        $solution = new Solution(title: 'Fix', description: 'See docs', links: $links);

        self::assertCount(2, $solution->links);
        self::assertSame('Docs', $solution->links[0]->label);
        self::assertSame('https://example.com/docs', $solution->links[0]->url);
    }

    #[Test]
    public function linksDefaultToEmpty(): void
    {
        $solution = new Solution(title: 'Fix', description: 'Do it');

        self::assertSame([], $solution->links);
    }

    #[Test]
    public function isReadonly(): void
    {
        $ref = new \ReflectionClass(Solution::class);

        self::assertTrue($ref->isReadOnly());
    }

    #[Test]
    public function isFinal(): void
    {
        $ref = new \ReflectionClass(Solution::class);

        self::assertTrue($ref->isFinal());
    }

    #[Test]
    public function storesEmptyTitle(): void
    {
        $solution = new Solution(title: '', description: 'desc');

        self::assertSame('', $solution->title);
    }

    #[Test]
    public function storesMultiLineDescription(): void
    {
        $desc = "Step 1: Do this\nStep 2: Do that\nStep 3: Done";
        $solution = new Solution(title: 'Steps', description: $desc);

        self::assertSame($desc, $solution->description);
    }

    #[Test]
    public function solutionLinkStoresLabel(): void
    {
        $link = new SolutionLink(label: 'PHP Docs', url: 'https://php.net');

        self::assertSame('PHP Docs', $link->label);
    }

    #[Test]
    public function solutionLinkStoresUrl(): void
    {
        $link = new SolutionLink(label: 'PHP', url: 'https://php.net');

        self::assertSame('https://php.net', $link->url);
    }

    #[Test]
    public function solutionLinkIsReadonly(): void
    {
        $ref = new \ReflectionClass(SolutionLink::class);

        self::assertTrue($ref->isReadOnly());
    }

    #[Test]
    public function solutionLinkIsFinal(): void
    {
        $ref = new \ReflectionClass(SolutionLink::class);

        self::assertTrue($ref->isFinal());
    }

    #[Test]
    public function solutionLinkWithSpecialCharsInUrl(): void
    {
        $url = 'https://example.com/search?q=test&page=1#section';
        $link = new SolutionLink(label: 'Search', url: $url);

        self::assertSame($url, $link->url);
    }
}
