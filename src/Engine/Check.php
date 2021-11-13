<?php

declare(strict_types = 1);

namespace Cadfael\Engine;

use Exception;

/**
 * A check is a basic unit of the system. It takes an entity, runs some check against it and may
 * return a report on the result of the check.
 */
interface Check
{
    /**
     * Does this check support the supplied entity? This is used to filter a list of entities so
     * only relevant ones are passed to the run function.
     *
     * @param mixed $entity
     * @return bool
     */
    public function supports($entity): bool;

    /**
     * Perform the check on the supplied entity.
     *
     * @param mixed $entity
     * @return Report|null
     * @throws Exception
     */
    public function run($entity): ?Report;

    /**
     * Provide a short and meaningful name for the check (64 characters is a good maximum length).
     * This will be displayed on the output to the user.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Provide a brief explaination for what this check does. This should not be particularly long
     * as a full breakdown of the reasoning can be provided as an external references (for anyone
     * who wants to understand the reasoning in more depth).
     *
     * Also, if your check can return different results based on what it finds, make that part of
     * the messages in the report rather than this description.
     *
     * An example of a good description could be:
     * - Identify any tables that may not be in use any more based on query usage.
     * - List all the indexes that MySQL will never use and thus is wasted disk space and processing power.
     *
     * @return string
     */
    public function getDescription(): string;

    /**
     * Provide an external reference to subsequent explaination of the check. This is useful when
     * the reasoning behind the check requires understanding some inner mechanism of MySQL that can't
     * be briefly explained in the description above.
     *
     * The external reference should, preferably, be hosted at https://github.com/xsist10/cadfael/wiki
     *
     * @return string
     */
    public function getReferenceUri(): string;
}
