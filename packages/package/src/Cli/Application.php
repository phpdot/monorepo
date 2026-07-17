<?php

declare(strict_types=1);

/**
 * phpdot/package CLI Application
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Package\Cli;

use PHPdot\Package\Cli\Command\BindingsCommand;
use PHPdot\Package\Cli\Command\ConfigCommand;
use PHPdot\Package\Cli\Command\ConfigListCommand;
use PHPdot\Package\Cli\Command\ListCommand;
use PHPdot\Package\Cli\Command\PathsCommand;
use PHPdot\Package\Cli\Command\ServicesCommand;
use PHPdot\Package\Cli\Command\ShowCommand;
use PHPdot\Package\PackageManager;
use Symfony\Component\Console\Application as SymfonyApplication;

final class Application extends SymfonyApplication
{
    /**
     * __construct.
     *
     * @param string $projectRoot
     */
    public function __construct(string $projectRoot)
    {
        parent::__construct('phpdot/package', '1.x');

        $manager = new PackageManager($projectRoot);

        $this->addCommands([
            new ListCommand($manager),
            new ShowCommand($manager),
            new PathsCommand($manager),
            new ConfigCommand($manager),
            new ConfigListCommand($manager),
            new ServicesCommand($manager),
            new BindingsCommand($manager),
        ]);
    }
}
