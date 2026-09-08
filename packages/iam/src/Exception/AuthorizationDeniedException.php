<?php

declare(strict_types=1);

/**
 * A denial from the PolicyGate's throwing path. Carries HTTP 403 via
 * getStatusCode() so the error-handler middleware renders a real Forbidden —
 * never a 500 (the gap the previous generation shipped with). The message
 * names only the action, never why it failed: denial reasons are for logs and
 * iam:why, not for the denied party.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Exception;

final class AuthorizationDeniedException extends IamException
{
    /**
     * A denial for the given action.
     *
     * @param string $action The denied action
     *
     * @return self
     */
    public static function denied(string $action): self
    {
        return new self(sprintf('Authorization denied for action [%s].', $action));
    }

    /**
     * The HTTP status this denial renders as.
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return 403;
    }
}
