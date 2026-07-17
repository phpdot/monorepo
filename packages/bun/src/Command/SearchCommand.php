<?php

declare(strict_types=1);

/**
 * Searches the npm registry for packages. Bun has no search command, so this hits the registry's
 * HTTP search API directly via {@see NpmRegistryClient} — it never needs the Bun binary.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Bun\Command;

use PHPdot\Bun\Registry\NpmRegistryClient;
use PHPdot\Bun\Registry\SearchResult;
use PHPdot\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'bun:search',
    description: 'Search the npm registry for packages.',
)]
final class SearchCommand extends Command
{
    private const int DESCRIPTION_WIDTH = 60;

    /**
     * Inject the npm registry client the command searches.
     *
     * @param NpmRegistryClient $registry
     */
    public function __construct(
        private readonly NpmRegistryClient $registry,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('term', InputArgument::REQUIRED, 'Search term')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Maximum number of results', '20');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /**
         * @var string $term
         */
        $term = $input->getArgument('term');
        $limitOption = $input->getOption('limit');
        $limit = is_numeric($limitOption) ? max(1, (int) $limitOption) : 20;

        $results = $this->registry->search($term, $limit);

        if ($results === []) {
            $this->warning($output, sprintf('No packages found for "%s".', $term));

            return self::SUCCESS;
        }

        $rows = array_map(
            fn(SearchResult $r): array => [
                'Name' => $r->name,
                'Version' => $r->version,
                'Description' => $this->truncate($r->description, self::DESCRIPTION_WIDTH),
                'Score' => number_format($r->score, 2),
            ],
            $results,
        );

        $this->table($output, $rows);

        return self::SUCCESS;
    }

    /**
     * Truncate a string to a maximum length, appending an ellipsis.
     *
     * @param string $text
     * @param int $width
     *
     * @return string
     */
    private function truncate(string $text, int $width): string
    {
        if (mb_strlen($text) <= $width) {
            return $text;
        }

        return mb_substr($text, 0, $width - 1) . '…';
    }
}
