<?php

declare(strict_types = 1);

namespace Cadfael\Engine;

interface Check
{
    /**
     * @param mixed $entity
     * @return bool
     */
    public function supports($entity): bool;

    /**
     * @param mixed $entity
     * @return Report|null
     * @throws \Exception
     */
    public function run($entity): ?Report;
}
