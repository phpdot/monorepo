<?php

declare(strict_types=1);

/**
 * iam:role:list — one page of the role list, grant counts attached. The same
 * search every screen takes; a terminal is just another caller.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Cli;

use PHPdot\Console\Command;
use PHPdot\Iam\Authorization\Contract\RoleRepositoryInterface;
use PHPdot\Iam\Authorization\DTO\RolesFilterDTO;
use PHPdot\Iam\Exception\IamException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'iam:role:list', description: 'List roles and their grant counts.')]
final class RoleListCommand extends Command
{
    use ReadsListOptions;

    public function __construct(
        private readonly RoleRepositoryInterface $roles,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('q', null, InputOption::VALUE_REQUIRED, 'Free text to match against the role name')
            ->addOption('page', null, InputOption::VALUE_REQUIRED, '1-based page number', '1')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Rows per page, at most 100', '100');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $page = $this->pageOption($input);

            $result = $this->roles->search(new RolesFilterDTO(
                q: $this->textOption($input, 'q'),
                _grants: true,
                page: $page,
                perPage: $this->limitOption($input),
            ));

            $rows = [];

            foreach ($result->page->items as $role) {
                $rows[] = [
                    'id' => (string) $role->id,
                    'name' => $role->name,
                    'description' => $role->description,
                    'flags' => $role->is_system ? 'system' : '',
                    'grants' => (string) ($role->_grants ?? 0),
                ];
            }

            if ($rows === []) {
                $this->comment($output, 'No roles matched.');

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
