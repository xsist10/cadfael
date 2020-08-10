<?php

declare(strict_types=1);

namespace Cadfael\Engine;

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
     * @return array<Report>
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
                    $reports[] = $report;
                }
            }
        }

        return $reports;
    }
}
