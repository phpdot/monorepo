<?php

declare(strict_types=1);

/**
 * console:clear — removes the compiled command cache so the next invocation
 * rediscovers. DELIBERATELY carries no #[AsCommand] attribute: discovery
 * must never find it and the cache must never contain it — the Application
 * registers this instance unconditionally, so the one command that fixes a
 * stale cache can never be hidden by one.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Console;

use PHPdot\Console\Cache\CommandCache;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ClearCommand extends Command
{
    /**
     * @param CommandCache|null $cache The application's command cache, when configured
     */
    public function __construct(
        private readonly null|CommandCache $cache = null,
    ) {
        parent::__construct('console:clear');

        $this->setDescription('Clear the compiled command cache (rebuilt on the next invocation).');
        $this->coroutine = false;
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->cache === null) {
            $this->comment($output, 'Command cache is disabled (no cachePath configured) — nothing to clear.');

            return self::SUCCESS;
        }

        if (!$this->cache->has()) {
            $this->comment($output, sprintf('Command cache is already empty (%s).', $this->cache->path()));

            return self::SUCCESS;
        }

        $this->cache->clear();
        $this->success($output, sprintf('Command cache cleared (%s) — the next invocation rediscovers.', $this->cache->path()));

        return self::SUCCESS;
    }
}
