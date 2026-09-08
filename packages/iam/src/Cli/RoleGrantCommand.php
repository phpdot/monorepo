<?php

declare(strict_types=1);

/**
 * iam:role:grant — write one grant edge, both ends by id.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Cli;

use PHPdot\Console\Command;
use PHPdot\Iam\Authorization\Contract\RoleManagerInterface;
use PHPdot\Iam\Authorization\DTO\GrantSaveDTO;
use PHPdot\Iam\Exception\IamException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'iam:role:grant', description: 'Grant a permission to a role.')]
final class RoleGrantCommand extends Command
{
    use ReadsStringArguments;

    public function __construct(
        private readonly RoleManagerInterface $manager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('role', InputArgument::REQUIRED, 'The role id')
            ->addArgument('permission', InputArgument::REQUIRED, 'The mirrored permission id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $grant = new GrantSaveDTO(
                role_id: $this->idArgument($input, 'role'),
                permission_id: $this->idArgument($input, 'permission'),
            );

            $this->manager->grant($grant);
            $this->success($output, sprintf(
                'Granted permission [%d] to role [%d].',
                $grant->permission_id,
                $grant->role_id,
            ));
        } catch (IamException $e) {
            $this->error($output, $e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
