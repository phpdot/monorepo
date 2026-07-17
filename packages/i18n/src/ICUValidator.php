<?php

declare(strict_types=1);

/**
 * Standalone ICU MessageFormat template validator.
 *
 * Use to syntax-check templates without invoking the full `Translator` —
 * useful in CLI tools, build pipelines, and unit tests for catalogs. Wraps
 * `ext-intl`'s `MessageFormatter` and surfaces both construction-time
 * (`IntlException`) and parse-time (`getErrorCode()`) failures uniformly.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\I18n;

use PHPdot\Container\Attribute\Singleton;

#[Singleton]
final class ICUValidator
{
    /**
     * Validate an ICU MessageFormat template.
     *
     * @param string $template The ICU message template to validate
     * @param string $locale The locale to validate against
     *
     * @return list<string> Error messages. Empty if valid.
     */
    public function validate(string $template, string $locale = 'en'): array
    {
        try {
            $formatter = new \MessageFormatter($locale, $template);
        } catch (\IntlException $e) {
            return [$e->getMessage()];
        }

        if ($formatter->getErrorCode() !== 0) {
            return [$formatter->getErrorMessage()];
        }

        return [];
    }

    /**
     * Check whether an ICU MessageFormat template is valid.
     *
     * @param string $template The ICU message template to check
     * @param string $locale The locale to validate against
     *
     * @return bool
     */
    public function isValid(string $template, string $locale = 'en'): bool
    {
        return $this->validate($template, $locale) === [];
    }
}
