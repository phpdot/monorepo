<?php

declare(strict_types=1);

/**
 * iam:role:create — persist a role that does not exist yet; the id is
 * storage's answer, not the caller's guess.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Cli;

use PHPdot\Console\Command;
use PHPdot\Iam\Authorization\Contract\RoleManagerInterface;
use PHPdot\Iam\Authorization\DTO\RoleSaveDTO;
use PHPdot\Iam\Exception\IamException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'iam:role:create', description: 'Create a role.')]
final class RoleCreateCommand extends Command
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
            ->addArgument('name', InputArgument::REQUIRED, 'The role name — the one identifier')
            ->addArgument('description', InputArgument::OPTIONAL, 'What the role is for', '');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $name = $this->stringArgument($input, 'name');
            $description = $input->getArgument('description');

            $this->manager->saveRole(new RoleSaveDTO(
                id: null,
                name: $name,
                description: is_string($description) ? $description : '',
            ));
            $this->success($output, sprintf('Role [%s] created.', $name));
        } catch (IamException $e) {
            $this->error($output, $e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
