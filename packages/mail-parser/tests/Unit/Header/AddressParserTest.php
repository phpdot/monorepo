<?php

/**
 * Unit tests for RFC 5322 address parsing including groups, comments, source routes, and malformed input recovery.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

declare(strict_types=1);

namespace PHPdot\Mail\Parser\Tests\Unit\Header;

use PHPdot\Mail\Parser\Header\AddressParser;
use PHPdot\Mail\Parser\Result\Address;
use PHPdot\Mail\Parser\Result\AddressGroup;
use PHPUnit\Framework\TestCase;

final class AddressParserTest extends TestCase
{
    private AddressParser $parser;

    protected function setUp(): void
    {
        $this->parser = new AddressParser();
    }

    public function testSimpleAddress(): void
    {
        $result = $this->parser->parse('user@example.com');
        $this->assertCount(1, $result);
        $this->assertInstanceOf(Address::class, $result[0]);
        $this->assertSame('user@example.com', $result[0]->address);
        $this->assertSame('', $result[0]->name);
    }

    public function testNameAngleAddr(): void
    {
        $result = $this->parser->parse('John Doe <john@example.com>');
        $this->assertCount(1, $result);
        $this->assertInstanceOf(Address::class, $result[0]);
        $this->assertSame('john@example.com', $result[0]->address);
        $this->assertSame('John Doe', $result[0]->name);
    }

    public function testQuotedDisplayName(): void
    {
        $result = $this->parser->parse('"Doe, John" <john@example.com>');
        $this->assertCount(1, $result);
        $this->assertSame('john@example.com', $result[0]->address);
        $this->assertSame('Doe, John', $result[0]->name);
    }

    public function testMultipleAddresses(): void
    {
        $result = $this->parser->parse('john@example.com, jane@example.com');
        $this->assertCount(2, $result);
        $this->assertSame('john@example.com', $result[0]->address);
        $this->assertSame('jane@example.com', $result[1]->address);
    }

    public function testMultipleNamedAddresses(): void
    {
        $result = $this->parser->parse('John Doe <john@example.com>, Jane Smith <jane@example.com>');
        $this->assertCount(2, $result);
        $this->assertSame('john@example.com', $result[0]->address);
        $this->assertSame('John Doe', $result[0]->name);
        $this->assertSame('jane@example.com', $result[1]->address);
        $this->assertSame('Jane Smith', $result[1]->name);
    }

    public function testGroup(): void
    {
        $result = $this->parser->parse('friends: john@a.com, jane@b.com;');
        $this->assertCount(1, $result);
        $this->assertInstanceOf(AddressGroup::class, $result[0]);
        $this->assertSame('friends', $result[0]->name);
        $this->assertCount(2, $result[0]->addresses);
        $this->assertSame('john@a.com', $result[0]->addresses[0]->address);
        $this->assertSame('jane@b.com', $result[0]->addresses[1]->address);
    }

    public function testCommentAsDisplayName(): void
    {
        $result = $this->parser->parse('john@example.com (John Doe)');
        $this->assertCount(1, $result);
        $this->assertSame('john@example.com', $result[0]->address);
        $this->assertSame('John Doe', $result[0]->name);
    }

    public function testEmptyInput(): void
    {
        $result = $this->parser->parse('');
        $this->assertSame([], $result);
    }

    public function testAngleBracketsOnly(): void
    {
        $result = $this->parser->parse('<john@example.com>');
        $this->assertCount(1, $result);
        $this->assertSame('john@example.com', $result[0]->address);
    }

    public function testAddressToString(): void
    {
        $addr = new Address('john@example.com', 'John Doe');
        $this->assertSame('"John Doe" <john@example.com>', (string) $addr);

        $addrNoName = new Address('john@example.com');
        $this->assertSame('john@example.com', (string) $addrNoName);
    }

    public function testGroupToString(): void
    {
        $group = new AddressGroup('friends', [
            new Address('a@b.com', 'Alice'),
            new Address('c@d.com'),
        ]);
        $this->assertSame('friends: "Alice" <a@b.com>, c@d.com;', (string) $group);
    }

    public function testObsoleteSourceRoute(): void
    {
        $result = $this->parser->parse('<@host1,@host2:user@example.com>');
        $this->assertCount(1, $result);
        $this->assertInstanceOf(Address::class, $result[0]);
        $this->assertSame('user@example.com', $result[0]->address);
    }

    public function testNestedComments(): void
    {
        $result = $this->parser->parse('user@example.com (outer (inner) comment)');
        $this->assertCount(1, $result);
        $this->assertInstanceOf(Address::class, $result[0]);
        $this->assertSame('user@example.com', $result[0]->address);
        // The comment should be extracted as the display name
        $this->assertNotEmpty($result[0]->name);
        $this->assertStringContainsString('outer', $result[0]->name);
        $this->assertStringContainsString('inner', $result[0]->name);
        $this->assertStringContainsString('comment', $result[0]->name);
    }

    public function testEscapedQuoteInDisplayName(): void
    {
        $result = $this->parser->parse('"John \"Johnny\" Doe" <john@example.com>');
        $this->assertCount(1, $result);
        $this->assertInstanceOf(Address::class, $result[0]);
        $this->assertSame('john@example.com', $result[0]->address);
        $this->assertSame('John "Johnny" Doe', $result[0]->name);
    }

    public function testEscapedParenInComment(): void
    {
        $result = $this->parser->parse('user@example.com (comment \) more)');
        $this->assertCount(1, $result);
        $this->assertInstanceOf(Address::class, $result[0]);
        $this->assertSame('user@example.com', $result[0]->address);
    }

    public function testMissingClosingAngleBracket(): void
    {
        $result = $this->parser->parse('John <john@example.com');
        $this->assertCount(1, $result);
        $this->assertInstanceOf(Address::class, $result[0]);
        $this->assertSame('john@example.com', $result[0]->address);
        $this->assertSame('John', $result[0]->name);
    }

    public function testEmptyGroup(): void
    {
        $result = $this->parser->parse('friends:;');
        $this->assertCount(1, $result);
        $this->assertInstanceOf(AddressGroup::class, $result[0]);
        $this->assertSame('friends', $result[0]->name);
        $this->assertCount(0, $result[0]->addresses);
    }

    public function testQuotedLocalPart(): void
    {
        $result = $this->parser->parse('"user.name"@example.com');
        $this->assertCount(1, $result);
        $this->assertInstanceOf(Address::class, $result[0]);
        $this->assertSame('"user.name"@example.com', $result[0]->address);
    }

    public function testMultipleGroupsMixedWithPlain(): void
    {
        $result = $this->parser->parse('team: a@b.com; c@d.com, group2: e@f.com;');
        $this->assertCount(3, $result);

        // First item: group "team" with one address
        $this->assertInstanceOf(AddressGroup::class, $result[0]);
        $this->assertSame('team', $result[0]->name);
        $this->assertCount(1, $result[0]->addresses);
        $this->assertSame('a@b.com', $result[0]->addresses[0]->address);

        // Second item: plain address
        $this->assertInstanceOf(Address::class, $result[1]);
        $this->assertSame('c@d.com', $result[1]->address);

        // Third item: group "group2" with one address
        $this->assertInstanceOf(AddressGroup::class, $result[2]);
        $this->assertSame('group2', $result[2]->name);
        $this->assertCount(1, $result[2]->addresses);
        $this->assertSame('e@f.com', $result[2]->addresses[0]->address);
    }

    public function testMalformedInputRecovery(): void
    {
        $result = $this->parser->parse('garbage, john@example.com, more garbage');
        // Should find the valid address
        $found = false;
        foreach ($result as $item) {
            if ($item instanceof Address && $item->address === 'john@example.com') {
                $found = true;
            }
        }
        $this->assertTrue($found, 'Should find john@example.com in malformed input');
    }

    public function testVeryLongAddressList(): void
    {
        $addrs = [];
        for ($i = 0; $i < 100; $i++) {
            $addrs[] = "user{$i}@example.com";
        }
        $input = implode(', ', $addrs);
        $result = $this->parser->parse($input);
        $this->assertCount(100, $result);
        $this->assertSame('user0@example.com', $result[0]->address);
        $this->assertSame('user99@example.com', $result[99]->address);
    }
}
