<?php

declare(strict_types=1);

namespace Cadfael\Engine\Entity;

use Cadfael\Engine\Entity;
use Cadfael\Engine\Entity\Query\EventsStatementsSummary;

class Query implements Entity
{
    protected Schema $schema;
    protected string $digest;
    protected EventsStatementsSummary $eventsStatementsSummary;

    public function __construct(string $digest)
    {
        $this->digest = $digest;
    }

    public function getName(): string
    {
        return $this->digest;
    }

    /**
     * Is this entity virtual (generated rather than stored on disk)?
     *
     * @return bool
     */
    public function isVirtual(): bool
    {
        return true;
    }

    /**
     * All entities should be able to return a string identifier.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->digest;
    }

    /**
     * @return Schema
     */
    public function getSchema(): Schema
    {
        return $this->schema;
    }

    /**
     * @param Schema $schema
     */
    public function setSchema(Schema $schema)
    {
        $this->schema = $schema;
    }

    public function getDigest(): string
    {
        return $this->digest;
    }

    /**
     * @codeCoverageIgnore
     * Skip coverage as this is a basic accessor. Remove if the accessor behaviour becomes more complicated.
     *
     * @return EventsStatementsSummary
     */
    public function getEventsStatementsSummary(): EventsStatementsSummary
    {
        return $this->eventsStatementsSummary;
    }

    /**
     * @codeCoverageIgnore
     * Skip coverage as this is a basic accessor. Remove if the accessor behaviour becomes more complicated.
     *
     * @param EventsStatementsSummary $eventsStatementsSummary
     */
    public function setEventsStatementsSummary(EventsStatementsSummary $eventsStatementsSummary): void
    {
        $this->eventsStatementsSummary = $eventsStatementsSummary;
    }
}
