<?php

declare(strict_types=1);

/**
 * Package Manager
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Package;

use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;
use PHPdot\Container\ContainerBuilder;
use PHPdot\Package\Contract\InstallHandler;
use PHPdot\Package\Generator\ConfigFileGenerator;
use PHPdot\Package\Generator\DefinitionGenerator;
use PHPdot\Package\Generator\ManifestGenerator;
use PHPdot\Package\Scanner\PackageMeta;
use PHPdot\Package\Scanner\PackageScanner;
use PHPdot\Package\Scanner\ScannedClass;
use ReflectionClass;
use RuntimeException;

final class PackageManager
{
    private const string PHPDOT_DIR = 'phpdot';
    private const string DEFINITIONS_FILE = 'definitions.php';
    private const string MANIFEST_FILE = 'manifest.php';

    private readonly string $basePath;
    private readonly string $vendorPath;
    private readonly string $configPath;

    /**
     * @var list<string>
     */
    private readonly array $exclude;

    /**
     * Create the package manager, auto-detecting the project root when omitted.
     *
     * @param string|null $basePath Absolute path to the project root. When null,
     *                              auto-detected from the Composer autoloader
     *                              that resolved this class.
     * @param list<string> $environments Environment names for config override blocks
     */
    public function __construct(
        ?string $basePath = null,
        private readonly array $environments = ['development', 'production', 'staging'],
    ) {
        if ($basePath === null) {
            $this->vendorPath = self::detectVendorPath();
            $this->basePath = self::detectProjectRoot();
        } else {
            $this->basePath = $basePath;
            $this->vendorPath = $basePath . '/' . self::resolveVendorDir($basePath);
        }

        [$configDir, $exclude] = self::readPhpdotExtra($this->basePath);

        $this->configPath = $this->basePath . '/' . $configDir;
        $this->exclude = $exclude;
    }

    /**
     * The project root (the directory of the root composer.json), taken from
     * Composer's runtime metadata. Authoritative even when the vendor directory
     * is relocated via a custom `vendor-dir` (e.g. `protected/vendor`) — no path
     * guessing. Used only when an explicit base path is not supplied.
     *
     * @return string
     */
    private static function detectProjectRoot(): string
    {
        $installPath = InstalledVersions::getRootPackage()['install_path'];
        $resolved = realpath($installPath);

        return $resolved !== false ? $resolved : $installPath;
    }

    /**
     * Detect the vendor directory from the Composer autoloader that registered
     * this class. Falls back to the first registered loader when there is only
     * one. With multiple loaders (e.g. global Composer tools), prefer the one
     * whose vendor path is an ancestor of this file.
     *
     * @return string
     */
    private static function detectVendorPath(): string
    {
        if (!class_exists(ClassLoader::class)) {
            throw new RuntimeException(
                'PackageManager could not auto-detect the project root: Composer autoloader is not loaded. '
                . 'Pass an explicit $basePath to the constructor.',
            );
        }

        $loaders = ClassLoader::getRegisteredLoaders();

        if ($loaders === []) {
            throw new RuntimeException(
                'PackageManager could not auto-detect the project root: no Composer autoloader is registered. '
                . 'Pass an explicit $basePath to the constructor.',
            );
        }

        if (count($loaders) === 1) {
            return array_key_first($loaders);
        }

        $selfFile = (string) new ReflectionClass(self::class)->getFileName();

        foreach (array_keys($loaders) as $vendorPath) {
            if (str_starts_with($selfFile, $vendorPath . DIRECTORY_SEPARATOR)) {
                return $vendorPath;
            }
        }

        return array_key_first($loaders);
    }

    /**
     * Resolve vendor dir.
     *
     * @param string $basePath
     *
     * @return string
     */
    private static function resolveVendorDir(string $basePath): string
    {
        $composerPath = $basePath . '/composer.json';

        if (!is_file($composerPath)) {
            return 'vendor';
        }

        /**
         * @var array<string, mixed> $composer
         */
        $composer = json_decode((string) file_get_contents($composerPath), true, 512, JSON_THROW_ON_ERROR);
        $config = is_array($composer['config'] ?? null) ? $composer['config'] : [];

        return is_string($config['vendor-dir'] ?? null) ? $config['vendor-dir'] : 'vendor';
    }

    /**
     * Read the `extra.phpdot` block from a package's composer.json.
     *
     * @param string $basePath
     *
     * @return array{0: string, 1: list<string>} [configDir, exclude]
     */
    private static function readPhpdotExtra(string $basePath): array
    {
        $composerPath = $basePath . '/composer.json';

        if (!is_file($composerPath)) {
            return ['config', []];
        }

        /**
         * @var array<string, mixed> $composer
         */
        $composer = json_decode((string) file_get_contents($composerPath), true, 512, JSON_THROW_ON_ERROR);
        $extra = is_array($composer['extra'] ?? null) ? $composer['extra'] : [];
        $phpdot = is_array($extra['phpdot'] ?? null) ? $extra['phpdot'] : [];

        $configDir = is_string($phpdot['config-dir'] ?? null) ? $phpdot['config-dir'] : 'config';
        $exclude = is_array($phpdot['exclude'] ?? null)
            ? array_values(array_filter($phpdot['exclude'], 'is_string'))
            : [];

        return [$configDir, $exclude];
    }

    /**
     * Load cached package definitions into the builder.
     *
     * @param ContainerBuilder $builder The container builder
     *
     * @return ContainerBuilder The same builder with definitions added
     */
    public function load(ContainerBuilder $builder): ContainerBuilder
    {
        $path = $this->definitionsPath();

        if (!is_file($path)) {
            return $builder;
        }

        /**
         * @var array<string, mixed> $definitions
         */
        $definitions = require $path;
        $builder->addDefinitions($definitions);

        return $builder;
    }

    /**
     * Rebuild.
     *
     * @return RebuildResult
     */
    public function rebuild(): RebuildResult
    {
        $previouslyOwnedConfigs = $this->readPreviouslyOwnedConfigs();

        $scanner = new PackageScanner();
        $scanResult = $scanner->scan($this->vendorPath, $this->exclude);

        $classes = $scanResult->classes;
        $packages = $scanResult->packages;

        $configGenerator = new ConfigFileGenerator();

        $prefix = rtrim($this->configPath, '/') . '/';
        $ownedConfigs = array_map(
            static fn(string $path): string => str_starts_with($path, $prefix)
                ? substr($path, strlen($prefix))
                : basename($path),
            $configGenerator->ownedPaths($classes, $this->configPath),
        );

        $defGenerator = new DefinitionGenerator();
        $defContent = $defGenerator->generate($classes, $packages);
        $this->writePhpdotFile(self::DEFINITIONS_FILE, $defContent);

        $manifestGenerator = new ManifestGenerator();
        $manifestContent = $manifestGenerator->generate(
            $classes,
            $packages,
            $ownedConfigs,
        );
        $this->writePhpdotFile(self::MANIFEST_FILE, $manifestContent);

        $generatedConfigs = $configGenerator->generate(
            $classes,
            $packages,
            $this->configPath,
            $this->environments,
        );

        $serviceCount = 0;
        $bindingCount = 0;
        $configCount = 0;
        $packageNames = [];

        foreach ($classes as $scanned) {
            if ($scanned->scope !== null) {
                $serviceCount++;
            }

            $bindingCount += count($scanned->binds);

            if ($scanned->configName !== null) {
                $configCount++;
            }

            $packageNames[$scanned->package] = true;
        }

        $installMessages = $this->runInstallHooks($classes);

        $orphanedConfigs = [];

        foreach (array_diff($previouslyOwnedConfigs, $ownedConfigs) as $name) {
            $path = $this->configPath . '/' . $name;

            if (is_file($path)) {
                $orphanedConfigs[] = $path;
            }
        }

        return new RebuildResult(
            packageCount: count($packageNames),
            serviceCount: $serviceCount,
            bindingCount: $bindingCount,
            configCount: $configCount,
            generatedConfigs: $generatedConfigs,
            orphanedConfigs: $orphanedConfigs,
            installMessages: $installMessages,
        );
    }

    /**
     * Invoke discovered #[InstallHook] handlers now that config files exist.
     * Handlers are idempotent by contract; this runs CLI-only at composer time.
     *
     * @param list<ScannedClass> $classes
     *
     * @return list<string> One-line messages returned by the handlers
     */
    private function runInstallHooks(array $classes): array
    {
        $messages = [];

        foreach ($classes as $scanned) {
            if (!$scanned->installHook) {
                continue;
            }

            $handler = $scanned->class;

            if (!is_a($handler, InstallHandler::class, true)) {
                continue;
            }

            $message = $handler::install($this->basePath, $this->configPath);

            if ($message !== null) {
                $messages[] = $message;
            }
        }

        return $messages;
    }

    /**
     * Read the `ownedConfigs` list from the previous manifest. Returns an
     * empty list on first run (no manifest yet) or when the manifest
     * predates this ledger format.
     *
     * @return list<string>
     */
    private function readPreviouslyOwnedConfigs(): array
    {
        $path = $this->phpdotDir() . '/' . self::MANIFEST_FILE;

        if (!is_file($path)) {
            return [];
        }

        /**
         * @var mixed $data
         */
        $data = require $path;

        if (!is_array($data)) {
            return [];
        }

        $configs = $data['ownedConfigs'] ?? [];

        return is_array($configs) ? array_values(array_filter($configs, 'is_string')) : [];
    }

    /**
     * Clear.
     *
     * @return void
     */
    public function clear(): void
    {
        $dir = $this->phpdotDir();

        $defPath = $dir . '/' . self::DEFINITIONS_FILE;

        if (is_file($defPath)) {
            unlink($defPath);
        }

        $manifestPath = $dir . '/' . self::MANIFEST_FILE;

        if (is_file($manifestPath)) {
            unlink($manifestPath);
        }
    }

    /**
     * Render the original default config content for a package's #[Config]
     * classes without writing anything to disk, so `package:config` can show
     * the pristine defaults without ever touching the developer's own file.
     *
     * @param string $package The composer package name (e.g. phpdot/database)
     *
     * @return array<string, string> Config file name => generated content
     */
    public function renderConfig(string $package): array
    {
        $scanResult = (new PackageScanner())->scan($this->vendorPath, $this->exclude);
        $generator = new ConfigFileGenerator();

        $rendered = [];

        foreach ($scanResult->classes as $scanned) {
            if ($scanned->package !== $package || $scanned->configName === null) {
                continue;
            }

            $meta = $scanResult->packages[$package] ?? new PackageMeta(name: $package);
            $rendered[$scanned->configName] = $generator->render($scanned, $meta, $this->environments);
        }

        return $rendered;
    }

    /**
     * Manifest.
     *
     * @return ?Manifest
     */
    public function manifest(): ?Manifest
    {
        $path = $this->phpdotDir() . '/' . self::MANIFEST_FILE;

        if (!is_file($path)) {
            return null;
        }

        /**
         * @var array{
         *     generated_at: string,
         *     packages: array<string, array{
         *         description: string,
         *         url: string,
         *         author: string,
         *         services: array<string, string>,
         *         bindings: array<string, string>,
         *         configs: array<string, string>
         *     }>
         *} $data
         */
        $data = require $path;

        $packages = [];

        foreach ($data['packages'] as $name => $info) {
            $packages[$name] = new PackageInfo(
                name: $name,
                description: $info['description'],
                url: $info['url'],
                author: $info['author'],
                services: $info['services'],
                configs: $info['configs'],
                bindings: $info['bindings'],
            );
        }

        return new Manifest(
            packages: $packages,
            generatedAt: $data['generated_at'],
        );
    }

    /**
     * Base path.
     *
     * @return string
     */
    public function basePath(): string
    {
        return $this->basePath;
    }

    /**
     * Vendor path.
     *
     * @return string
     */
    public function vendorPath(): string
    {
        return $this->vendorPath;
    }

    /**
     * Config path.
     *
     * @return string
     */
    public function configPath(): string
    {
        return $this->configPath;
    }

    /**
     * Definitions path.
     *
     * @return string
     */
    public function definitionsPath(): string
    {
        return $this->phpdotDir() . '/' . self::DEFINITIONS_FILE;
    }

    /**
     * Manifest path.
     *
     * @return string
     */
    public function manifestPath(): string
    {
        return $this->phpdotDir() . '/' . self::MANIFEST_FILE;
    }

    /**
     * Phpdot dir.
     *
     * @return string
     */
    private function phpdotDir(): string
    {
        return $this->vendorPath . '/' . self::PHPDOT_DIR;
    }

    /**
     * Write phpdot file.
     *
     * @param string $filename
     * @param string $content
     *
     * @return void
     */
    private function writePhpdotFile(string $filename, string $content): void
    {
        $dir = $this->phpdotDir();

        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        $path = $dir . '/' . $filename;
        $tmp = $path . '.tmp.' . getmypid();
        file_put_contents($tmp, $content);
        rename($tmp, $path);

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($path, true);
        }
    }
}
