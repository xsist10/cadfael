<?php

declare(strict_types=1);

namespace Cadfael\Engine\Entity;

use Cadfael\Engine\Entity;
use Doctrine\DBAL\Connection;

class Database implements Entity
{
    /**
     * @var Schema[]
     */
    private array $schemas;

    /**
     * @var string[]
     */
    private array $variables;

    /**
     * @var string[]
     */
    private array $status;

    private Connection $connection;

    public function __construct(?Connection $connection)
    {
        if ($connection) {
            $this->setConnection($connection);
        }
    }

    public function getName(): string
    {
        return $this->getConnection()->getHost() . ':' . $this->getConnection()->getPort();
    }

    public function __toString(): string
    {
        return $this->getName();
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }

    public function setConnection(Connection $connection): void
    {
        $this->connection = $connection;
    }

    /**
     * @return string[]
     */
    public function getVariables(): array
    {
        return $this->variables;
    }

    /**
     * @param string[] $variables
     */
    public function setVariables(array $variables): void
    {
        $this->variables = $variables;
    }

    /**
     * @return string[]
     */
    public function getStatus(): array
    {
        return $this->status;
    }

    /**
     * @param string[] $status
     */
    public function setStatus(array $status): void
    {
        $this->status = $status;
    }

    /**
     * @return Schema[]
     */
    public function getSchemas(): array
    {
        return $this->schemas;
    }

    /**
     * @param Schema[] $schemas
     */
    public function setSchemas(array $schemas): void
    {
        array_walk($schemas, function (Schema $schema) {
            $schema->setDatabase($this);
        });
        $this->schemas = $schemas;
    }

    public function getVersion(): string
    {
        return $this->variables['version'];
    }


    public function isVirtual(): bool
    {
        return false;
    }
}
