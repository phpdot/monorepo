<?php

declare(strict_types=1);

/**
 * iam:why — explain a capability decision: the role ids an actor holds, the
 * names behind them, and which of those grants the asked permission. A
 * person reads names; the addresses underneath stay ids.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Cli;

use PHPdot\Console\Command;
use PHPdot\Iam\Authorization\Contract\PermissionProviderInterface;
use PHPdot\Iam\Authorization\Contract\RoleRepositoryInterface;
use PHPdot\Iam\Authorization\DTO\RolesFilterDTO;
use PHPdot\Iam\Exception\IamException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'iam:why', description: 'Explain a capability decision: the roles and grants behind it.')]
final class WhyCommand extends Command
{
    use IdentityArgument;

    public function __construct(
        private readonly PermissionProviderInterface $provider,
        private readonly RoleRepositoryInterface $roles,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('identity', InputArgument::REQUIRED, "The actor as 'type:id' (user:42, guest)")
            ->addArgument('permission', InputArgument::REQUIRED, 'The permission key');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $identity = $this->identityArgument($input, 'identity');
            $permission = $this->stringArgument($input, 'permission');

            $held = $this->provider->rolesFor($identity);
            $output->writeln(sprintf('actor <info>%s:%s</info>', $identity->type(), (string) ($identity->id() ?? '')));

            if ($held === []) {
                $output->writeln('  holds no roles');
                $output->writeln('<error>DENY</error>');

                return self::FAILURE;
            }

            $names = [];

            foreach ($this->roles->lookups(new RolesFilterDTO(ids: $held)) as $lookup) {
                $names[$lookup->id] = $lookup->name;
            }

            $output->writeln('  roles: ' . implode(', ', array_map(
                static fn(int $roleId): string => sprintf('%s (#%d)', $names[$roleId] ?? '?', $roleId),
                $held,
            )));

            $granting = [];

            foreach ($held as $roleId) {
                if (in_array($permission, $this->roles->permissionsOf($roleId), true)) {
                    $granting[] = $roleId;
                }
            }

            if ($granting === []) {
                $output->writeln(sprintf('  no held role grants [%s]', $permission));
                $output->writeln('<error>DENY</error>');

                return self::FAILURE;
            }

            foreach ($granting as $roleId) {
                $output->writeln(sprintf('  role <info>%s</info> grants [%s]', $names[$roleId] ?? sprintf('#%d', $roleId), $permission));
            }

            $output->writeln('<info>ALLOW</info>');

            return self::SUCCESS;
        } catch (IamException $e) {
            $this->error($output, $e->getMessage());

            return self::FAILURE;
        }
    }
}
