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
    public function __toString(): string;
}
