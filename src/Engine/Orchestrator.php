<?php

declare(strict_types=1);

namespace Cadfael\Engine;

use Exception;

class Orchestrator
{
    /**
     * @var array<Check>
     */
    protected array $checks = [];

    /**
     * @var array<Entity>
     */
    protected array $entities = [];

    /**
     * @var array<callable>
     */
    protected array $callbacks = [];

    /**
     * @return array<Check>
     */
    public function getChecks(): array
    {
        return $this->checks;
    }

    public function addChecks(Check ...$checks): void
    {
        $this->checks = array_merge($this->checks, $checks);
    }

    /**
     * @return array<Entity>
     */
    public function getEntities(): array
    {
        return $this->entities;
    }

    public function addEntities(Entity ...$entities): void
    {
        $this->entities = array_merge($this->entities, $entities);
    }

    /**
     * @return callable[]
     */
    public function getCallbacks(): array
    {
        return $this->callbacks;
    }

    /**
     * @param callable[] $callbacks
     */
    public function addCallbacks(callable ...$callbacks): void
    {
        $this->callbacks = array_merge($this->callbacks, $callbacks);
    }

    /**
     * @return array<Report>
     * @throws Exception
     */
    public function run(): array
    {
        $reports = [];

        foreach ($this->entities as $entity) {
            foreach ($this->checks as $check) {
                if (!$check->supports($entity)) {
                    continue;
                }
                $report = $check->run($entity);
                if (!is_null($report)) {
                    // Trigger all our callbacks
                    foreach ($this->callbacks as $callback) {
                        call_user_func($callback, $report);
                    }
                    $reports[] = $report;
                }
            }
        }

        return $reports;
    }
}
