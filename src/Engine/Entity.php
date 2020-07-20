<?php

declare(strict_types = 1);

namespace Cadfael\Engine;

interface Entity
{
    public function getName(): string;

    /**
     * Is this entity virtual (generated rather than stored on disk)?
     *
     * @return bool
     */
    public function isVirtual(): bool;

    /**
     * All entities should be able to return a string identifier.
     *
     * @return string
     */
    public function __toString(): string;
}
