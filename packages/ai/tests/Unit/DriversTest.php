<?php

declare(strict_types=1);

namespace PHPdot\Ai\Tests\Unit;

use PHPdot\Ai\Bridge\Anthropic\Driver as AnthropicDriver;
use PHPdot\Ai\Bridge\OpenAi\Driver as OpenAiDriver;
use PHPdot\Ai\Exception\AiException;
use PHPdot\Ai\Exception\LlmError;
use PHPdot\Ai\Registry\DriverFactories;
use PHPdot\Ai\Registry\Drivers;
use PHPdot\Config\Configuration;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The registry over a REAL Configuration built from the fixture directory — the
 * class is final, so a fixture is the only double it allows, and it needs none.
 */
final class DriversTest extends TestCase
{
    private Drivers $drivers;

    protected function setUp(): void
    {
        $this->drivers = new Drivers(
            new Configuration(__DIR__ . '/../Support/config'),
            new DriverFactories(),
        );
    }

    #[Test]
    public function providersAreNamedBlocksOverShapes(): void
    {
        self::assertSame(['anthropic', 'openai', 'groq'], $this->drivers->names());

        $catalogue = $this->drivers->catalogue();

        self::assertSame('anthropic', $catalogue[0]['name']);
        self::assertSame('anthropic', $catalogue[0]['shape']);
        self::assertSame('Anthropic', $catalogue[0]['label']);
        self::assertTrue($catalogue[0]['configured']);

        self::assertSame('groq', $catalogue[2]['name']);
        self::assertSame('openai', $catalogue[2]['shape'], 'a second provider rides the same wire');
        self::assertSame(['llama-test' => 'Llama Test'], $catalogue[2]['models']);
    }

    #[Test]
    public function theNamedDefaultWinsAndAFallbackIsConfigured(): void
    {
        self::assertSame('anthropic', $this->drivers->default());
        self::assertSame(['gpt-test' => 'GPT Test'], $this->drivers->models('openai'));
        self::assertTrue($this->drivers->isConfigured('groq'));
    }

    #[Test]
    public function eachNameBuildsItsShape(): void
    {
        self::assertInstanceOf(AnthropicDriver::class, $this->drivers->for('anthropic'));
        self::assertInstanceOf(OpenAiDriver::class, $this->drivers->for('openai'));
        self::assertInstanceOf(OpenAiDriver::class, $this->drivers->for('groq'));
    }

    #[Test]
    public function aMissingNameIsRefused(): void
    {
        $this->expectException(LlmError::class);
        $this->expectExceptionMessage('No provider is configured under [mistral].');

        $this->drivers->for('mistral');
    }

    #[Test]
    public function anUnknownWireShapeIsRefusedByName(): void
    {
        $drivers = new Drivers(
            new Configuration(__DIR__ . '/../Support/broken'),
            new DriverFactories(),
        );

        $this->expectException(LlmError::class);
        $this->expectExceptionMessage('declares the wire shape [bedrock], which no registered factory serves');

        $drivers->for('bedrock-vendor');
    }

    #[Test]
    public function theErrorIsCatchableAsTheDomain(): void
    {
        try {
            $this->drivers->for('nobody');

            self::fail('for() resolved a provider that does not exist.');
        } catch (AiException $failure) {
            self::assertInstanceOf(LlmError::class, $failure);
        }
    }
}
