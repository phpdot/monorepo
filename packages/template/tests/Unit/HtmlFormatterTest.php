<?php

declare(strict_types=1);

namespace PHPdot\Template\Tests\Unit;

use PHPdot\Template\Exception\FormatterUnavailableException;
use PHPdot\Template\Exception\InvalidFormatOptionsException;
use PHPdot\Template\HtmlFormatter;
use PHPdot\Template\TemplateConfig;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

final class HtmlFormatterTest extends TestCase
{
    private const string DOCUMENT = '<!DOCTYPE html><html lang="en"><head><title>T</title>'
        . '<meta name="csrf-token" content="abc"></head><body><p>x</p></body></html>';

    private const string FRAGMENT = '<div class="card"><h3>Title</h3><p>Body</p></div>';

    public function test_disabled_returns_input_untouched(): void
    {
        $formatter = new HtmlFormatter(new TemplateConfig());

        self::assertSame(self::DOCUMENT, $formatter->format(self::DOCUMENT));
    }

    public function test_disabled_needs_no_extension(): void
    {
        $this->expectNotToPerformAssertions();

        new HtmlFormatter(new TemplateConfig(format: false));
    }

    #[RequiresPhpExtension('tidy')]
    public function test_document_is_indented(): void
    {
        $output = $this->formatter()->format(self::DOCUMENT);

        self::assertStringContainsString("<html lang=\"en\">\n", $output);
        self::assertStringContainsString('    <head>', $output);
    }

    /**
     * The failure this class exists to prevent: a document formatted in
     * fragment mode loses its entire head.
     */
    #[RequiresPhpExtension('tidy')]
    public function test_document_keeps_its_head(): void
    {
        $output = $this->formatter()->format(self::DOCUMENT);

        self::assertStringContainsString('<title>', $output);
        self::assertStringContainsString('csrf-token', $output);
        self::assertStringContainsString('<!DOCTYPE html>', $output);
    }

    /**
     * The mirror failure: a fragment formatted in document mode gains an
     * html/head wrapper it never asked for.
     */
    #[RequiresPhpExtension('tidy')]
    public function test_fragment_is_not_wrapped_in_a_document(): void
    {
        $output = $this->formatter()->format(self::FRAGMENT);

        self::assertStringNotContainsString('<html', $output);
        self::assertStringNotContainsString('<head', $output);
        self::assertStringContainsString('<div class="card">', $output);
        self::assertStringContainsString('<h3>', $output);
    }

    #[RequiresPhpExtension('tidy')]
    public function test_leading_whitespace_still_detects_a_document(): void
    {
        $output = $this->formatter()->format("\n  " . self::DOCUMENT);

        self::assertStringContainsString('<title>', $output);
    }

    #[RequiresPhpExtension('tidy')]
    public function test_options_override_the_defaults(): void
    {
        $formatter = new HtmlFormatter(new TemplateConfig(format: true, formatOptions: ['indent-spaces' => 2]));

        self::assertStringContainsString("\n  <head>", $formatter->format(self::DOCUMENT));
    }

    /**
     * Flexibility is the point: any tidy option may be passed, not only the
     * ones the package names in its defaults.
     */
    #[RequiresPhpExtension('tidy')]
    public function test_options_may_add_settings_the_package_does_not_define(): void
    {
        $formatter = new HtmlFormatter(new TemplateConfig(
            format: true,
            formatOptions: ['doctype' => 'omit', 'indent' => false],
        ));

        self::assertStringNotContainsString('<!DOCTYPE', $formatter->format(self::DOCUMENT));
    }

    /**
     * `show-body-only` is decided per input and cannot be overridden, because
     * the wrong value destroys content.
     */
    #[RequiresPhpExtension('tidy')]
    public function test_show_body_only_cannot_be_forced_on_a_document(): void
    {
        $formatter = new HtmlFormatter(new TemplateConfig(format: true, formatOptions: ['show-body-only' => true]));

        self::assertStringContainsString('<title>', $formatter->format(self::DOCUMENT));
    }

    #[RequiresPhpExtension('tidy')]
    public function test_empty_input_is_returned_as_is(): void
    {
        self::assertSame('   ', $this->formatter()->format('   '));
    }

    #[RequiresPhpExtension('tidy')]
    public function test_whitespace_significant_elements_are_preserved(): void
    {
        $html = '<div><pre>keep   spacing' . "\n" . '  indented</pre>'
            . '<textarea name="bio">line one' . "\n" . 'line two</textarea></div>';

        $output = $this->formatter()->format($html);

        self::assertStringContainsString("keep   spacing\n  indented", $output);
        self::assertStringContainsString("line one\nline two", $output);
    }

    #[RequiresPhpExtension('tidy')]
    public function test_unknown_option_fails_at_construction_not_at_render(): void
    {
        $this->expectException(InvalidFormatOptionsException::class);
        $this->expectExceptionMessageMatches('/not-a-real-option/');

        new HtmlFormatter(new TemplateConfig(format: true, formatOptions: ['not-a-real-option' => true]));
    }

    #[RequiresPhpExtension('tidy')]
    public function test_unacceptable_option_value_fails_at_construction(): void
    {
        $this->expectException(InvalidFormatOptionsException::class);
        $this->expectExceptionMessageMatches('/indent-spaces/');

        new HtmlFormatter(new TemplateConfig(format: true, formatOptions: ['indent-spaces' => 'banana']));
    }

    /**
     * Bad options are not validated when formatting is off — nothing will
     * ever be handed to tidy, so nothing should fail.
     */
    public function test_disabled_does_not_validate_options(): void
    {
        $this->expectNotToPerformAssertions();

        new HtmlFormatter(new TemplateConfig(format: false, formatOptions: ['not-a-real-option' => true]));
    }

    /**
     * The message is the feature: whoever hits this must be told both ways
     * out without leaving the stack trace.
     */
    public function test_unavailable_error_names_both_remedies(): void
    {
        $message = (new FormatterUnavailableException())->getMessage();

        self::assertStringContainsString('template.format', $message);
        self::assertStringContainsString('install ext-tidy', $message);
        self::assertStringContainsString('set template.format to false', $message);
    }

    public function test_enabled_without_the_extension_throws_an_explained_error(): void
    {
        $this->expectException(FormatterUnavailableException::class);
        $this->expectExceptionMessageMatches('/install ext-tidy.+or set template\.format to false/is');

        new HtmlFormatter(new TemplateConfig(format: true), tidyAvailable: false);
    }

    private function formatter(): HtmlFormatter
    {
        return new HtmlFormatter(new TemplateConfig(format: true));
    }
}
