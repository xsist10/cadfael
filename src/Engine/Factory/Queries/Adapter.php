<?php

declare(strict_types=1);

namespace Cadfael\Engine\Factory\Queries;

interface Adapter
{
    public function supports(): bool;
    public function process(array $fragment);
}
