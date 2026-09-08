<?php

declare(strict_types=1);

/**
 * iam:policy:list — one page of the policy inventory mirror: the rule
 * classes that exist and the resources they judge.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Cli;

use PHPdot\Console\Command;
use PHPdot\Iam\Authorization\Contract\PolicyRepositoryInterface;
use PHPdot\Iam\Authorization\DTO\PoliciesFilterDTO;
use PHPdot\Iam\Exception\IamException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'iam:policy:list', description: 'List the discovered policy inventory.')]
final class PolicyListCommand extends Command
{
    use ReadsListOptions;

    public function __construct(
        private readonly PolicyRepositoryInterface $policies,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('q', null, InputOption::VALUE_REQUIRED, 'Free text to match against the class and resource')
            ->addOption('page', null, InputOption::VALUE_REQUIRED, '1-based page number', '1')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Rows per page, at most 100', '100');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $page = $this->pageOption($input);

            $result = $this->policies->search(new PoliciesFilterDTO(
                q: $this->textOption($input, 'q'),
                page: $page,
                perPage: $this->limitOption($input),
            ));

            $rows = [];

            foreach ($result->page->items as $policy) {
                $rows[] = [
                    'id' => (string) $policy->id,
                    'policy' => $policy->policy,
                    'resource' => $policy->resource,
                ];
            }

            if ($rows === []) {
                $this->comment($output, 'No policies matched.');

                return self::SUCCESS;
            }

            $this->table($output, $rows);

            if ($result->page->has_more) {
                $this->comment($output, sprintf('More pages exist — pass --page %d or narrow with --q.', $page + 1));
            }

            return self::SUCCESS;
        } catch (IamException $e) {
            $this->error($output, $e->getMessage());

            return self::FAILURE;
        }
    }
}
