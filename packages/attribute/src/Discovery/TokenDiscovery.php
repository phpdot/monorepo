<?php

declare(strict_types=1);

/**
 * Class discovery by tokenizing PHP sources, no autoloading required.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Attribute\Discovery;

use PhpToken;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class TokenDiscovery
{
    /**
     * Find class-like declarations by tokenizing PHP files under the directories.
     *
     * @param list<string> $directories
     * @param list<string> $namespaces
     * @param list<string> $excludePatterns
     *
     * @return list<class-string>
     */
    public function discover(
        array $directories,
        array $namespaces = [],
        array $excludePatterns = [],
    ): array {
        $classes = [];

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory),
            );

            /**
             * @var SplFileInfo $file
             */
            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $filePath = $file->getRealPath();

                if ($filePath === false) {
                    continue;
                }

                $discovered = $this->extractClasses($filePath);

                foreach ($discovered as $className) {
                    if ($namespaces !== []) {
                        $matchesNamespace = false;

                        foreach ($namespaces as $namespace) {
                            if (str_starts_with($className, $namespace)) {
                                $matchesNamespace = true;
                                break;
                            }
                        }

                        if (!$matchesNamespace) {
                            continue;
                        }
                    }

                    if ($this->isExcluded($className, $excludePatterns)) {
                        continue;
                    }

                    $classes[] = $className;
                }
            }
        }

        sort($classes);

        return $classes;
    }

    /**
     * Pull fully-qualified class-like names out of one file's token stream.
     *
     * @param string $filePath
     *
     * @return list<class-string>
     */
    private function extractClasses(string $filePath): array
    {
        $contents = file_get_contents($filePath);

        if ($contents === false) {
            return [];
        }

        $tokens = PhpToken::tokenize($contents);
        $namespace = '';
        $i = 0;
        $count = count($tokens);
        $classes = [];

        while ($i < $count) {
            $token = $tokens[$i];

            if ($token->id === T_NAMESPACE) {
                $i++;

                while ($i < $count && $tokens[$i]->id === T_WHITESPACE) {
                    $i++;
                }

                $parts = [];

                while ($i < $count && $tokens[$i]->text !== ';' && $tokens[$i]->text !== '{') {
                    if ($tokens[$i]->id !== T_WHITESPACE) {
                        $parts[] = $tokens[$i]->text;
                    }

                    $i++;
                }

                $namespace = implode('', $parts);
            }

            if ($token->id === T_CLASS || $token->id === T_INTERFACE || $token->id === T_TRAIT || $token->id === T_ENUM) {
                if ($token->id === T_CLASS) {
                    $j = $i - 1;

                    while ($j >= 0 && $tokens[$j]->id === T_WHITESPACE) {
                        $j--;
                    }

                    if ($j >= 0 && $tokens[$j]->id === T_NEW) {
                        $i++;
                        continue;
                    }
                }

                $i++;

                while ($i < $count && $tokens[$i]->id === T_WHITESPACE) {
                    $i++;
                }

                if ($i < $count && $tokens[$i]->id === T_STRING) {
                    /**
                     * @var class-string $className
                     */
                    $className = $namespace !== '' ? $namespace . '\\' . $tokens[$i]->text : $tokens[$i]->text;
                    $classes[] = $className;
                }
            }

            $i++;
        }

        return $classes;
    }

    /**
     * Whether the class name matches any exclude pattern.
     *
     * @param list<string> $excludePatterns
     * @param string $className
     *
     * @return bool
     */
    private function isExcluded(string $className, array $excludePatterns): bool
    {
        foreach ($excludePatterns as $pattern) {
            if (fnmatch($pattern, $className)) {
                return true;
            }
        }

        return false;
    }
}
