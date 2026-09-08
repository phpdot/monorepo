<?php

declare(strict_types=1);

/**
 * can() was handed something that is not a well-formed policy — a class that
 * does not exist or does not carry PolicyInterface, an instance that is not
 * invokable, or a rule answering non-bool. Always a programming error, always
 * loud: a broken policy must never read as a quiet deny.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Exception;

final class InvalidPolicyException extends IamException
{
    /**
     * The named class is missing or does not carry PolicyInterface.
     *
     * @param string $policy The offending class name
     *
     * @return self
     */
    public static function notAPolicy(string $policy): self
    {
        return new self(sprintf('[%s] is not a policy — it must exist and implement PolicyInterface.', $policy));
    }

    /**
     * The resolved policy instance is not invokable.
     *
     * @param string $policy The offending class name
     *
     * @return self
     */
    public static function notInvokable(string $policy): self
    {
        return new self(sprintf('Policy [%s] is not invokable — it must declare __invoke(IdentityInterface, IamResource): bool.', $policy));
    }

    /**
     * The class violates the scan-enforced __invoke convention.
     *
     * @param string $policy The offending class name
     *
     * @return self
     */
    public static function malformed(string $policy): self
    {
        return new self(sprintf(
            'Malformed policy [%s] — a policy declares exactly public __invoke(IdentityInterface $identity, <T extends IamResource> $resource): bool.',
            $policy,
        ));
    }

    /**
     * The policy answered something other than a bool.
     *
     * @param string $policy The offending class name
     *
     * @return self
     */
    public static function nonBoolDecision(string $policy): self
    {
        return new self(sprintf('Policy [%s] answered a non-bool — a decision is true or false, nothing else.', $policy));
    }
}
