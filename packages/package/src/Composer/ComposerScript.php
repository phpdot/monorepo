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
use RuntimeException;

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

        $result = self::rebuildIsolated($event, $basePath);

        $io = $event->getIO();
        $io->write(sprintf(
            '<info>phpdot/package:</info> %d package%s, %d service%s, %d binding%s cached.',
            $result['packageCount'],
            $result['packageCount'] === 1 ? '' : 's',
            $result['serviceCount'],
            $result['serviceCount'] === 1 ? '' : 's',
            $result['bindingCount'],
            $result['bindingCount'] === 1 ? '' : 's',
        ));

        foreach ($result['generatedConfigs'] as $path) {
            $relative = str_replace($basePath . '/', '', $path);
            $io->write(sprintf('<info>phpdot/package:</info> generated %s', $relative));
        }

        foreach ($result['installMessages'] as $message) {
            $io->write(sprintf('<info>%s</info>', $message));
        }

        $orphans = $result['orphanedConfigs'];

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

    /**
     * Run the definitions rebuild in a clean PHP process on the project autoloader.
     *
     * This process is composer's: its phar bundles composer's own dependency
     * versions, and they sit ahead of the project's in the autoloader chain. The
     * scan reflects every installed class, so any class implementing an interface
     * under a newer major than the phar's copy (psr/log v3 against the bundled
     * v1, for one) is an uncatchable link-time error here. A subprocess loading
     * nothing but the freshly dumped project autoloader reflects every class
     * against the versions the application actually runs.
     *
     * @param Event $event The composer event carrying the vendor-dir configuration.
     * @param string $basePath The project root (directory of composer.json).
     *
     * @return array<string, mixed> The rebuild result as decoded from the subprocess.
     */
    private static function rebuildIsolated(Event $event, string $basePath): array
    {
        $vendorDir = $event->getComposer()?->getConfig()->get('vendor-dir');
        $vendorDir = is_string($vendorDir) && $vendorDir !== '' ? $vendorDir : 'vendor';

        if (!is_file($vendorDir . '/autoload.php') && is_file($basePath . '/' . $vendorDir . '/autoload.php')) {
            $vendorDir = $basePath . '/' . $vendorDir;
        }

        $code = <<<'PHP'
            require $argv[1] . '/autoload.php';

            $result = (new PHPdot\Package\PackageManager($argv[2]))->rebuild();

            echo json_encode([
                'packageCount'    => $result->packageCount,
                'serviceCount'    => $result->serviceCount,
                'bindingCount'    => $result->bindingCount,
                'generatedConfigs' => $result->generatedConfigs,
                'installMessages' => $result->installMessages,
                'orphanedConfigs' => $result->orphanedConfigs,
            ], JSON_THROW_ON_ERROR);
            PHP;

        $process = proc_open(
            [PHP_BINARY, '-d', 'display_errors=stderr', '-r', $code, '--', $vendorDir, $basePath],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        if (!is_resource($process)) {
            throw new RuntimeException('phpdot/package: could not spawn the isolated rebuild process.');
        }

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exit = proc_close($process);

        if ($exit !== 0) {
            throw new RuntimeException('phpdot/package: isolated rebuild failed: ' . trim($stderr));
        }

        $decoded = json_decode($stdout, true);

        if (!is_array($decoded)) {
            throw new RuntimeException('phpdot/package: isolated rebuild returned no result: ' . trim($stderr));
        }

        return $decoded;
    }
}
