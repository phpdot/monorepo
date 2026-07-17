<?php

declare(strict_types=1);

/**
 * Flow-control marker — when present anywhere in a field's rule chain, the
 * validator stops processing that chain at the first failure.
 *
 * Position-independent: `[new Bail(), ...]` and `[..., new Bail()]` behave
 * identically. Only the presence of `Bail` in the chain matters; rules before
 * it run normally and the chain bails on the first one that fails.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use PHPdot\Validator\Contract\RuleInterface;
use PHPdot\Validator\ValidationContext;

final class Bail implements RuleInterface
{
    public function passes(mixed $value, ValidationContext $context): bool
    {
        return true;
    }

    public function withError(\PHPdot\Error\ErrorCodeInterface $code): static
    {
        return $this;
    }

    public function code(): ?\PHPdot\Error\ErrorCodeInterface
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function params(ValidationContext $context): array
    {
        return [];
    }
}
