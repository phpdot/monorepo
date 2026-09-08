<?php

declare(strict_types=1);

/**
 * iam:permission:sync — THE ONLY PLACE DISCOVERY RUNS. Scans the host's
 * declared vocabulary (inside execute(), never at construction) and mirrors
 * BOTH halves into storage: permissions (upsert; undeclared keys marked
 * orphaned, never deleted; root kept granted the entire active catalog) and
 * the policy inventory (edgeless — vanished classes are removed). Code is
 * the source of truth; the mirror is the runtime read surface; this command
 * is the bridge, run at deploy.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Cli;

use PHPdot\Console\Command;
use PHPdot\Iam\Authorization\Contract\PermissionRepositoryInterface;
use PHPdot\Iam\Authorization\Contract\PolicyRepositoryInterface;
use PHPdot\Iam\Authorization\Discovery\DiscoveryPaths;
use PHPdot\Iam\Authorization\Discovery\IamScan;
use PHPdot\Iam\Exception\IamException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'iam:permission:sync', description: 'Mirror the declared permissions and policy inventory into storage.')]
final class PermissionSyncCommand extends Command
{
    /**
     * @param IamScan $scan The discovery subprocess runner
     * @param DiscoveryPaths $paths The host's scanned directories
     * @param PermissionRepositoryInterface $permissions The permission mirror
     * @param PolicyRepositoryInterface $policies The policy inventory mirror
     */
    public function __construct(
        private readonly IamScan $scan,
        private readonly DiscoveryPaths $paths,
        private readonly PermissionRepositoryInterface $permissions,
        private readonly PolicyRepositoryInterface $policies,
    ) {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $result = $this->scan->run($this->paths->directories);

            $report = $this->permissions->sync($result['permissions']);

            $this->success($output, sprintf(
                'Permissions: %d added, %d updated, %d orphaned.',
                count($report['added']),
                count($report['updated']),
                count($report['orphaned']),
            ));

            foreach ($report['orphaned'] as $key) {
                $this->warning($output, sprintf('Orphaned (declared nowhere, kept in storage): [%s]', $key));
            }

            $inventory = $this->policies->sync($result['policies']);

            $this->success($output, sprintf(
                'Policies: %d added, %d removed.',
                count($inventory['added']),
                count($inventory['removed']),
            ));

            return self::SUCCESS;
        } catch (IamException $e) {
            $this->error($output, $e->getMessage());

            return self::FAILURE;
        }
    }
}
