<?php

declare(strict_types=1);

/**
 * iam:has — one-shot possession check for an explicit identity: does the
 * grant set contain the key. Exits 0 when held, 1 when not — scriptable.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Cli;

use PHPdot\Console\Command;
use PHPdot\Iam\Authorization\Contract\PermissionProviderInterface;
use PHPdot\Iam\Exception\IamException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'iam:has', description: 'Check whether an identity holds a permission.')]
final class HasCommand extends Command
{
    use IdentityArgument;

    /**
     * @param PermissionProviderInterface $provider The capability storage seam
     */
    public function __construct(
        private readonly PermissionProviderInterface $provider,
    ) {
        parent::__construct();
    }

    /**
     * Define identity and permission arguments.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->addArgument('identity', InputArgument::REQUIRED, "The actor as 'type:id' (user:42, guest)")
            ->addArgument('permission', InputArgument::REQUIRED, 'The permission key');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $identity = $this->identityArgument($input, 'identity');
            $permission = $this->stringArgument($input, 'permission');

            $allowed = in_array($permission, $this->provider->permissionsFor($identity), true);

            $output->writeln($allowed ? '<info>ALLOW</info>' : '<error>DENY</error>');

            return $allowed ? self::SUCCESS : self::FAILURE;
        } catch (IamException $e) {
            $this->error($output, $e->getMessage());

            return self::FAILURE;
        }
    }
}
