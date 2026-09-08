<?php

declare(strict_types=1);

/**
 * iam:role:delete — remove a role and its edges, addressed by id; system
 * roles refuse.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Cli;

use PHPdot\Console\Command;
use PHPdot\Iam\Authorization\Contract\RoleManagerInterface;
use PHPdot\Iam\Exception\IamException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'iam:role:delete', description: 'Delete a role (system roles refuse).')]
final class RoleDeleteCommand extends Command
{
    use ReadsStringArguments;

    public function __construct(
        private readonly RoleManagerInterface $manager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('role', InputArgument::REQUIRED, 'The role id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $roleId = $this->idArgument($input, 'role');

            $this->manager->deleteRole($roleId);
            $this->success($output, sprintf('Role [%d] deleted.', $roleId));
        } catch (IamException $e) {
            $this->error($output, $e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
