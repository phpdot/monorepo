<?php

declare(strict_types=1);

/**
 * Composer Script
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Package\Composer;

use Composer\Factory;
use Composer\Script\Event;
use PHPdot\Package\PackageManager;

final class ComposerScript
{
    /**
     * Post autoload dump.
     *
     * @param Event $event
     *
     * @return void
     */
    public static function postAutoloadDump(Event $event): void
    {
        $composerFile = (string) Factory::getComposerFile();
        $resolved = realpath($composerFile);
        $basePath = dirname($resolved !== false ? $resolved : $composerFile);

        $manager = new PackageManager($basePath);
        $result = $manager->rebuild();

        $io = $event->getIO();
        $io->write(sprintf(
            '<info>phpdot/package:</info> %d package%s, %d service%s, %d binding%s cached.',
            $result->packageCount,
            $result->packageCount === 1 ? '' : 's',
            $result->serviceCount,
            $result->serviceCount === 1 ? '' : 's',
            $result->bindingCount,
            $result->bindingCount === 1 ? '' : 's',
        ));

        foreach ($result->generatedConfigs as $path) {
            $relative = str_replace($basePath . '/', '', $path);
            $io->write(sprintf('<info>phpdot/package:</info> generated %s', $relative));
        }

        foreach ($result->installMessages as $message) {
            $io->write(sprintf('<info>%s</info>', $message));
        }

        $orphans = $result->orphanedConfigs;

        if ($orphans !== []) {
            $io->write('');
            $io->write('<warning>phpdot/package: orphaned files (no longer owned by an installed package, may contain customisations):</warning>');

            foreach ($orphans as $path) {
                $relative = str_replace($basePath . '/', '', $path);
                $io->write(sprintf('<warning>phpdot/package:</warning>   %s', $relative));
            }

            $io->write('<warning>phpdot/package: review and delete manually if no longer needed.</warning>');
        }
    }
}
