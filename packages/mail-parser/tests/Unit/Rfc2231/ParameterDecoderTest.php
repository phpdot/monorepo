<?php

/**
 * Unit tests for RFC 2231 parameter decoding including continuations, charset encoding, and percent-decoding edge cases.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

declare(strict_types=1);

namespace PHPdot\Mail\Parser\Tests\Unit\Rfc2231;

use PHPdot\Mail\Parser\Content\CharsetConverter;
use PHPdot\Mail\Parser\Rfc2231\ParameterDecoder;
use PHPUnit\Framework\TestCase;

final class ParameterDecoderTest extends TestCase
{
    private ParameterDecoder $decoder;

    protected function setUp(): void
    {
        $this->decoder = new ParameterDecoder(new CharsetConverter());
    }

    public function testRegularParamsPassThrough(): void
    {
        $params = ['charset' => 'utf-8', 'format' => 'flowed'];
        $result = $this->decoder->decode($params);

        $this->assertSame('utf-8', $result['charset']);
        $this->assertSame('flowed', $result['format']);
    }

    public function testSingleExtendedParam(): void
    {
        // filename*=utf-8''%C3%A9l%C3%A8ves.pdf
        $params = ['filename*' => "utf-8''%C3%A9l%C3%A8ves.pdf"];
        $result = $this->decoder->decode($params);

        $this->assertSame('élèves.pdf', $result['filename']);
    }

    public function testExtendedParamWithLanguage(): void
    {
        $params = ['filename*' => "utf-8'en'test%20file.pdf"];
        $result = $this->decoder->decode($params);

        $this->assertSame('test file.pdf', $result['filename']);
    }

    public function testContinuationPlain(): void
    {
        $params = [
            'filename*0' => 'very_long_',
            'filename*1' => 'filename.pdf',
        ];
        $result = $this->decoder->decode($params);

        $this->assertSame('very_long_filename.pdf', $result['filename']);
    }

    public function testContinuationEncoded(): void
    {
        $params = [
            'filename*0*' => "utf-8''%C3%A9l%C3%A8ves",
            'filename*1*' => '%20rapport.pdf',
        ];
        $result = $this->decoder->decode($params);

        $this->assertSame('élèves rapport.pdf', $result['filename']);
    }

    public function testContinuationMixedEncodedAndPlain(): void
    {
        $params = [
            'filename*0*' => "utf-8''%C3%A9l%C3%A8ves",
            'filename*1' => ' rapport',
            'filename*2*' => '%20final.pdf',
        ];
        $result = $this->decoder->decode($params);

        $this->assertSame('élèves rapport final.pdf', $result['filename']);
    }

    public function testEmptyParams(): void
    {
        $this->assertSame([], $this->decoder->decode([]));
    }

    public function testMixedRegularAndExtended(): void
    {
        $params = [
            'charset' => 'utf-8',
            'filename*' => "utf-8''test%20file.pdf",
        ];
        $result = $this->decoder->decode($params);

        $this->assertSame('utf-8', $result['charset']);
        $this->assertSame('test file.pdf', $result['filename']);
    }

    public function testNonSequentialSections(): void
    {
        // Missing *1 — should concatenate what's available
        $params = [
            'filename*0' => 'part0',
            'filename*2' => 'part2',
        ];
        $result = $this->decoder->decode($params);

        $this->assertArrayHasKey('filename', $result);
        $this->assertStringContainsString('part0', $result['filename']);
        $this->assertStringContainsString('part2', $result['filename']);
    }

    public function testMissingSectionZeroWithCharset(): void
    {
        // filename*1*=utf-8''test — charset undefined, handle gracefully
        $params = [
            'filename*1*' => "utf-8''test",
        ];
        $result = $this->decoder->decode($params);

        $this->assertArrayHasKey('filename', $result);
        $this->assertStringContainsString('test', $result['filename']);
    }

    public function testInvalidPercentEncoding(): void
    {
        // %ZZ is invalid — rawurldecode leaves it as-is
        $params = ['filename*' => "utf-8''test%ZZvalue"];
        $result = $this->decoder->decode($params);

        $this->assertArrayHasKey('filename', $result);
        $this->assertStringContainsString('test', $result['filename']);
        $this->assertStringContainsString('value', $result['filename']);
    }

    public function testTrailingPercent(): void
    {
        // Trailing % stays as-is via rawurldecode
        $params = ['filename*' => "utf-8''test%"];
        $result = $this->decoder->decode($params);

        $this->assertArrayHasKey('filename', $result);
        $this->assertStringContainsString('test', $result['filename']);
    }

    public function testEmptyCharsetName(): void
    {
        // Empty charset: ''value
        $params = ['filename*' => "''value"];
        $result = $this->decoder->decode($params);

        $this->assertSame('value', $result['filename']);
    }

    public function testDuplicateSectionNumbers(): void
    {
        // Two filename*0 entries — last wins (PHP array behavior)
        $params = [
            'filename*0' => 'first',
        ];
        // Simulate duplicate by overwriting (PHP arrays can't have duplicate keys,
        // but we test that the last value is used)
        $params['filename*0'] = 'second';
        $params['filename*1'] = '.pdf';
        $result = $this->decoder->decode($params);

        $this->assertSame('second.pdf', $result['filename']);
    }
}
