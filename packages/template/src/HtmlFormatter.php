<?php

declare(strict_types=1);

/**
 * Re-indents rendered output through ext-tidy.
 *
 * Formatting cannot happen at compile time: a compiled Twig template is a
 * generator that yields static fragments interleaved with runtime values, so
 * the finished document only exists once rendering has run. It belongs here,
 * at the engine, rather than in each application — because the one rule that
 * makes it safe is easy to get wrong.
 *
 * That rule is `show-body-only`, and it is decided PER INPUT, never by
 * configuration: tidy given a fragment in document mode invents an `<html>`
 * and `<head>` around it, and tidy given a document in fragment mode silently
 * DISCARDS the `<head>`. This class picks the mode from the markup itself, so
 * neither can happen — which is exactly the knowledge a template package
 * should own once instead of every consumer rediscovering it.
 *
 * Disabled is the default and costs nothing: `format()` returns its input, so
 * callers need no conditional.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Template;

use PHPdot\Container\Attribute\Singleton;
use PHPdot\Template\Exception\FormatterUnavailableException;
use PHPdot\Template\Exception\InvalidFormatOptionsException;
use Throwable;
use tidy;
use TypeError;
use ValueError;

#[Singleton]
final readonly class HtmlFormatter
{
    /**
     * House defaults: indent, and change nothing else. `wrap => 0` keeps long
     * lines whole (a wrapped attribute value is a real edit, not a cosmetic
     * one); `wrap-script-literals` leaves inline scripts and embedded JSON
     * alone; the merge switches stop tidy collapsing markup a template wrote
     * deliberately; `tidy-mark` keeps its generator meta out of the head.
     *
     * Every one of these is overridable, and any tidy option not listed here
     * may be added, through `template.formatOptions`.
     *
     * @var array<string, bool|int|string>
     */
    private const array DEFAULTS = [
        'indent' => true,
        'indent-spaces' => 4,
        'wrap' => 0,
        'tidy-mark' => false,
        'output-html' => true,
        'char-encoding' => 'utf8',
        'newline' => 'LF',
        'preserve-entities' => true,
        'quote-ampersand' => false,
        'drop-empty-elements' => false,
        'wrap-script-literals' => false,
        'merge-divs' => false,
        'merge-spans' => false,
    ];

    private bool $enabled;

    /**
     * The developer's options merged over the house defaults.
     *
     * @var array<string, bool|int|string>
     */
    private array $options;

    private string $encoding;

    /**
     * @param TemplateConfig $config Supplies the on/off flag and the option overrides
     * @param bool|null $tidyAvailable Overrides the ext-tidy probe so the
     *                                 unavailable path is testable on a host that has the extension; null
     *                                 (the production value) probes extension_loaded('tidy')
     *
     * @throws FormatterUnavailableException If formatting is enabled without ext-tidy.
     */
    public function __construct(TemplateConfig $config, bool|null $tidyAvailable = null)
    {
        $this->enabled = $config->format;

        if (!$this->enabled) {
            $this->options = [];
            $this->encoding = 'utf8';

            return;
        }

        if (!($tidyAvailable ?? extension_loaded('tidy'))) {
            throw new FormatterUnavailableException();
        }

        $options = [...self::DEFAULTS, ...$config->formatOptions];
        $encoding = $options['char-encoding'] ?? 'utf8';

        $this->options = $options;
        $this->encoding = is_string($encoding) ? $encoding : 'utf8';

        $this->verify();
    }

    /**
     * Run the configured options past tidy once, so a bad one is a boot-time
     * error naming the option rather than a ValueError on every render.
     *
     * @throws InvalidFormatOptionsException If tidy rejects an option name or value.
     *
     * @return void
     */
    private function verify(): void
    {
        try {
            tidy::repairString('<p>probe</p>', [...$this->options, 'show-body-only' => true], $this->encoding);
        } catch (TypeError | ValueError $e) {
            throw new InvalidFormatOptionsException($e->getMessage(), $e);
        }
    }

    /**
     * Format rendered markup, or return it untouched when disabled.
     *
     * Fail-open at runtime: markup tidy cannot parse comes back exactly as it
     * arrived. Formatting is a courtesy and must never be the reason a page
     * is lost.
     *
     * @param string $html Rendered output — a whole document or a fragment
     *
     * @return string
     */
    public function format(string $html): string
    {
        if (!$this->enabled || trim($html) === '') {
            return $html;
        }

        $options = $this->options;
        $options['show-body-only'] = !$this->isDocument($html);

        try {
            $formatted = tidy::repairString($html, $options, $this->encoding);
        } catch (Throwable) {
            return $html;
        }

        return $formatted !== '' ? $formatted : $html;
    }

    /**
     * Whether the markup is a whole document rather than a fragment.
     *
     * Decided on the opening tag, which is the only signal that survives
     * rendering: a layout starts with a doctype or `<html>`, a partial with
     * anything else.
     *
     * @param string $html The rendered markup
     *
     * @return bool
     */
    private function isDocument(string $html): bool
    {
        $start = strtolower(ltrim($html, " \t\n\r\0\x0B\u{FEFF}"));

        return str_starts_with($start, '<!doctype') || str_starts_with($start, '<html');
    }
}
