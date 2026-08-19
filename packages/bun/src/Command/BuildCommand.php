<?php

declare(strict_types=1);

/**
 * Bundles entrypoint(s) with `bun build`, mapping console options to bun build flags.
 *
 * With --watch the command is long-lived: it rebuilds on change, streams output, and exits cleanly
 * when interrupted (SIGINT/SIGTERM forwarded to bun).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Bun\Command;

use PHPdot\Bun\Build\BuildOptions;
use PHPdot\Bun\Bun;
use PHPdot\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'bun:build',
    description: 'Bundle entrypoint(s) with bun build.',
)]
final class BuildCommand extends Command
{
    /**
     * Inject the Bun service the command drives.
     *
     * @param Bun $bun
     */
    public function __construct(
        private readonly Bun $bun,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('entry', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'Entrypoint file(s)')
            ->addOption('out-dir', null, InputOption::VALUE_REQUIRED, 'Output directory (--outdir)')
            ->addOption('out-file', null, InputOption::VALUE_REQUIRED, 'Output file (--outfile)')
            ->addOption('target', null, InputOption::VALUE_REQUIRED, 'Execution target: browser|bun|node')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Module format: esm|cjs|iife')
            ->addOption('minify', null, InputOption::VALUE_NONE, 'Enable all minification')
            ->addOption('minify-syntax', null, InputOption::VALUE_NONE, 'Minify syntax')
            ->addOption('minify-whitespace', null, InputOption::VALUE_NONE, 'Minify whitespace')
            ->addOption('minify-identifiers', null, InputOption::VALUE_NONE, 'Minify identifiers')
            ->addOption('splitting', null, InputOption::VALUE_NONE, 'Enable code splitting')
            ->addOption('sourcemap', null, InputOption::VALUE_REQUIRED, 'Sourcemap: linked|inline|external|none')
            ->addOption('hashed-names', null, InputOption::VALUE_NONE, 'Hash entrypoint output filenames')
            ->addOption('chunk-naming', null, InputOption::VALUE_REQUIRED, 'Chunk filename pattern, e.g. [name]-[hash].[ext]')
            ->addOption('asset-naming', null, InputOption::VALUE_REQUIRED, 'Asset filename pattern, e.g. [name]-[hash].[ext]')
            ->addOption('metafile', null, InputOption::VALUE_REQUIRED, 'Write build metadata JSON to this path')
            ->addOption('define', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Define a global, K=V (repeatable)')
            ->addOption('external', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Keep a package external (repeatable)')
            ->addOption('banner', null, InputOption::VALUE_REQUIRED, 'Prepend a banner to the output')
            ->addOption('footer', null, InputOption::VALUE_REQUIRED, 'Append a footer to the output')
            ->addOption('drop', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Drop an identifier, e.g. console (repeatable)')
            ->addOption('watch', null, InputOption::VALUE_NONE, 'Rebuild on change (long-lived)')
            ->addOption('root', null, InputOption::VALUE_REQUIRED, 'Root directory for multiple entry points')
            ->addOption('public-path', null, InputOption::VALUE_REQUIRED, 'Prefix prepended to import paths in bundled code')
            ->addOption('entry-naming', null, InputOption::VALUE_REQUIRED, 'Entry filename pattern, e.g. [dir]/[name]-[hash].[ext] (wins over --hashed-names)')
            ->addOption('css-chunking', null, InputOption::VALUE_NONE, 'Chunk CSS shared between entrypoints')
            ->addOption('keep-names', null, InputOption::VALUE_NONE, 'Preserve function and class names when minifying')
            ->addOption('reject-unresolved', null, InputOption::VALUE_NONE, 'Fail on unresolvable dynamic imports')
            ->addOption('packages', null, InputOption::VALUE_REQUIRED, 'Dependency handling: external|bundle')
            ->addOption('loader', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Per-extension loader, .ext:loader (repeatable)')
            ->addOption('conditions', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Custom export condition (repeatable)')
            ->addOption('env', null, InputOption::VALUE_REQUIRED, "Inline env vars: inline|disable|prefix like 'PUBLIC_*'")
            ->addOption('metafile-md', null, InputOption::VALUE_REQUIRED, 'Write the module-graph markdown to this path')
            ->addOption('no-clear-screen', null, InputOption::VALUE_NONE, 'Keep terminal scrollback in watch mode');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /**
         * @var list<string> $entry
         */
        $entry = $input->getArgument('entry');

        $options = new BuildOptions(
            outDir: $this->string($input, 'out-dir'),
            outFile: $this->string($input, 'out-file'),
            target: $this->string($input, 'target'),
            format: $this->string($input, 'format'),
            minify: (bool) $input->getOption('minify'),
            minifySyntax: (bool) $input->getOption('minify-syntax'),
            minifyWhitespace: (bool) $input->getOption('minify-whitespace'),
            minifyIdentifiers: (bool) $input->getOption('minify-identifiers'),
            splitting: (bool) $input->getOption('splitting'),
            sourcemap: $this->string($input, 'sourcemap'),
            hashedNames: (bool) $input->getOption('hashed-names'),
            chunkNaming: $this->string($input, 'chunk-naming'),
            assetNaming: $this->string($input, 'asset-naming'),
            metafile: $this->string($input, 'metafile'),
            define: $this->stringList($input, 'define'),
            external: $this->stringList($input, 'external'),
            banner: $this->string($input, 'banner'),
            footer: $this->string($input, 'footer'),
            drop: $this->stringList($input, 'drop'),
            watch: (bool) $input->getOption('watch'),
            root: $this->string($input, 'root'),
            publicPath: $this->string($input, 'public-path'),
            entryNaming: $this->string($input, 'entry-naming'),
            cssChunking: (bool) $input->getOption('css-chunking'),
            keepNames: (bool) $input->getOption('keep-names'),
            rejectUnresolved: (bool) $input->getOption('reject-unresolved'),
            packages: $this->packages($input),
            loader: $this->stringList($input, 'loader'),
            conditions: $this->stringList($input, 'conditions'),
            env: $this->string($input, 'env'),
            metafileMd: $this->string($input, 'metafile-md'),
            noClearScreen: (bool) $input->getOption('no-clear-screen'),
        );

        return $this->bun->buildWith($entry, $options);
    }

    /**
     * Read the --packages option, narrowed to the two modes bun accepts.
     *
     * @param InputInterface $input
     *
     * @return 'external'|'bundle'|null
     */
    private function packages(InputInterface $input): null|string
    {
        $value = $this->string($input, 'packages');

        return $value === 'external' || $value === 'bundle' ? $value : null;
    }

    /**
     * Read a string option from the input, or null when it is unset.
     *
     * @param InputInterface $input
     * @param string $name
     *
     * @return ?string
     */
    private function string(InputInterface $input, string $name): null|string
    {
        $value = $input->getOption($name);

        return is_string($value) ? $value : null;
    }

    /**
     * Reads a repeatable string option into a list, dropping any non-string values.
     *
     * @param InputInterface $input
     * @param string $name
     *
     * @return list<string>
     */
    private function stringList(InputInterface $input, string $name): array
    {
        $value = $input->getOption($name);
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_string(...)));
    }
}
