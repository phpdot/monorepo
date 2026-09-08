<?php

declare(strict_types=1);

/**
 * iam:permission:list — one page of the permission mirror, storage's copy of
 * the declared vocabulary, ids and life-stage included. Orphans stay
 * visible: an administrator sweeping up dead grants asks for exactly them.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Cli;

use PHPdot\Console\Command;
use PHPdot\Iam\Authorization\Contract\PermissionRepositoryInterface;
use PHPdot\Iam\Authorization\DTO\PermissionsFilterDTO;
use PHPdot\Iam\Authorization\Enum\PermissionStatus;
use PHPdot\Iam\Exception\IamException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'iam:permission:list', description: 'List the mirrored permission vocabulary.')]
final class PermissionListCommand extends Command
{
    use ReadsListOptions;

    public function __construct(
        private readonly PermissionRepositoryInterface $permissions,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('q', null, InputOption::VALUE_REQUIRED, 'Free text to match against the key and name')
            ->addOption('root', null, InputOption::VALUE_NONE, 'Only root-only permissions')
            ->addOption('orphaned', null, InputOption::VALUE_NONE, 'Only permissions declared nowhere in code')
            ->addOption('page', null, InputOption::VALUE_REQUIRED, '1-based page number', '1')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Rows per page, at most 100', '100');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $page = $this->pageOption($input);
            $rootOnly = is_bool($input->getOption('root')) && $input->getOption('root');
            $orphaned = is_bool($input->getOption('orphaned')) && $input->getOption('orphaned');

            $result = $this->permissions->search(new PermissionsFilterDTO(
                q: $this->textOption($input, 'q'),
                is_root: $rootOnly ? true : null,
                status: $orphaned ? PermissionStatus::Orphaned : null,
                page: $page,
                perPage: $this->limitOption($input),
            ));

            $rows = [];

            foreach ($result->page->items as $permission) {
                $rows[] = [
                    'id' => (string) $permission->id,
                    'key' => $permission->key,
                    'name' => $permission->name,
                    'flags' => trim(sprintf('%s%s', $permission->is_root ? 'root-only ' : '', $permission->status->value)),
                    'declared by' => $permission->declared_by,
                ];
            }

            if ($rows === []) {
                $this->comment($output, 'No permissions matched.');

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
