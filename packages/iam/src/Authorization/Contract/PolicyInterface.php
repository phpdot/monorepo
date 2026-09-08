<?php

declare(strict_types=1);

/**
 * Marks a class as a policy — ONE contextual rule, called directly. The
 * interface is deliberately empty: PHP forbids narrowing parameter types in
 * implementations, so the invocation shape is a convention the scan enforces
 * fail-fast instead — exactly one public __invoke(IdentityInterface $identity,
 * <T extends IamResource> $resource): bool, natively typed to the concrete
 * resource. Marking does two jobs: AuthorizerInterface::can() refuses classes that do
 * not carry it, and discovery inventories every policy for the admin surface
 * (iam:policy:list, the admin UI later). Policies resolve through the
 * container per call — inject what the rule needs (AuthorizerInterface for has(),
 * office-hours config, a pool) and keep the class final readonly.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authorization\Contract;

interface PolicyInterface {}
