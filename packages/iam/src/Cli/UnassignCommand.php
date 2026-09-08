<?php

declare(strict_types=1);

/**
 * iam:unassign — remove one assignment edge: an identity, a role by id.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Cli;

use PHPdot\Console\Command;
use PHPdot\Iam\Authorization\Contract\RoleManagerInterface;
use PHPdot\Iam\Authorization\DTO\AssignmentSaveDTO;
use PHPdot\Iam\Exception\IamException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'iam:unassign', description: 'Remove a role from an identity.')]
final class UnassignCommand extends Command
{
    use IdentityArgument;

    public function __construct(
        private readonly RoleManagerInterface $manager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('identity', InputArgument::REQUIRED, "The identity as 'type:id' (user:42)")
            ->addArgument('role', InputArgument::REQUIRED, 'The role id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $identity = $this->identityArgument($input, 'identity');
            $roleId = $this->idArgument($input, 'role');

            $this->manager->unassign(new AssignmentSaveDTO($identity, $roleId));
            $this->success($output, sprintf(
                'Unassigned role [%d] from %s:%s.',
                $roleId,
                $identity->type(),
                (string) ($identity->id() ?? ''),
            ));
        } catch (IamException $e) {
            $this->error($output, $e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
