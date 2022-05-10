<?php

declare(strict_types=1);

namespace Cadfael\Engine\Entity;

use Cadfael\Engine\Entity;
use Cadfael\Engine\Exception\InvalidSchema;
use Cadfael\Engine\Exception\MySQL\UnknownVersion;
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

    /**
     * @var array<Account>
     */
    protected array $accounts;

    /**
     * @var array<Tablespace>
     */
    protected array $tablespaces;

    private Connection $connection;

    public function __construct(?Connection $connection)
    {
        if ($connection) {
            $this->setConnection($connection);
        }
    }

    public function getName(): string
    {
        return $this->getConnection()->getHost() .
            ($this->getConnection()->getPort() ? ':' . $this->getConnection()->getPort() : '');
    }

    public function __toString(): string
    {
        return $this->getName();
    }

    /**
     * @codeCoverageIgnore
     * Skip coverage as this is a basic accessor. Remove if the accessor behaviour becomes more complicated.
     *
     * @return Connection
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * @codeCoverageIgnore
     * Skip coverage as this is a basic accessor. Remove if the accessor behaviour becomes more complicated.
     *
     * @param Connection $connection
     */
    public function setConnection(Connection $connection): void
    {
        $this->connection = $connection;
    }

    /**
     * @codeCoverageIgnore
     * Skip coverage as this is a basic accessor. Remove if the accessor behaviour becomes more complicated.
     *
     * @return array<Account>
     */
    public function getAccounts(): array
    {
        return $this->accounts;
    }

    public function getAccount(string $username, string $host): ?Account
    {
        $accounts = array_filter($this->accounts, function ($account) use ($username, $host) {
            return $account->getName() === $username
                && (
                    $account->getHost() === $host
                    || $account->getHost() === '%'
                );
        });

        if (count($accounts)) {
            return array_shift($accounts);
        }

        return null;
    }

    /**
     * @codeCoverageIgnore
     * Skip coverage as this is a basic accessor. Remove if the accessor behaviour becomes more complicated.
     *
     * @param Account ...$accounts
     */
    public function setAccounts(Account...$accounts): void
    {
        $this->accounts = $accounts;
    }

    /**
     * @codeCoverageIgnore
     * Skip coverage as this is a basic accessor. Remove if the accessor behaviour becomes more complicated.
     *
     * @param Account $account
     */
    public function addAccount(Account $account): void
    {
        $this->accounts[] = $account;
    }

    /**
     * @codeCoverageIgnore
     * Skip coverage as this is a basic accessor. Remove if the accessor behaviour becomes more complicated.
     *
     * @return array<Tablespace>
     */
    public function getTablespaces(): array
    {
        return $this->tablespaces;
    }

    public function getTablespace(int $id): ?Tablespace
    {
        foreach ($this->tablespaces as $tablespace) {
            if ($tablespace->getId() === $id) {
                return $tablespace;
            }
        }

        return null;
    }

    /**
     * @codeCoverageIgnore
     * Skip coverage as this is a basic accessor. Remove if the accessor behaviour becomes more complicated.
     *
     * @param Tablespace ...$tablespaces
     */
    public function setTablespaces(Tablespace...$tablespaces): void
    {
        $this->tablespaces = $tablespaces;
    }

    /**
     * @codeCoverageIgnore
     * Skip coverage as this is a basic accessor. Remove if the accessor behaviour becomes more complicated.
     *
     * @return string[]
     */
    public function getVariables(): array
    {
        return $this->variables;
    }

    /**
     * @codeCoverageIgnore
     * Skip coverage as this is a basic accessor. Remove if the accessor behaviour becomes more complicated.
     *
     * @param string[] $variables
     */
    public function setVariables(array $variables): void
    {
        $this->variables = $variables;
    }

    /**
     * @codeCoverageIgnore
     * Skip coverage as this is a basic accessor. Remove if the accessor behaviour becomes more complicated.
     *
     * @return string[]
     */
    public function getStatus(): array
    {
        return $this->status;
    }

    public function hasPerformanceSchema(): bool
    {
        // If we have a performance_schema = "ON" | "OFF" flag, use that
        if (!empty($this->variables['performance_schema'])) {
            return $this->variables['performance_schema'] === 'ON';
        }

        // Otherwise check to see if we have any keys in the variables that begin with performance_schema_*
        return count(array_filter(array_keys($this->variables), function ($key) {
            return strpos(strtolower($key), 'performance_schema_') === 0;
        })) > 0;
    }

    /**
     * @codeCoverageIgnore
     * Skip coverage as this is a basic accessor. Remove if the accessor behaviour becomes more complicated.
     *
     * @param string[] $status
     */
    public function setStatus(array $status): void
    {
        $this->status = $status;
    }

    /**
     * @codeCoverageIgnore
     * Skip coverage as this is a basic accessor. Remove if the accessor behaviour becomes more complicated.
     *
     * @return Schema[]
     */
    public function getSchemas(): array
    {
        return $this->schemas;
    }

    /**
     * @codeCoverageIgnore
     * Skip coverage as this is a basic accessor. Remove if the accessor behaviour becomes more complicated.
     *
     * @return Schema
     * @throws InvalidSchema
     */
    public function getSchema($name): Schema
    {
        foreach ($this->getSchemas() as $schema) {
            if ($schema->getName() === $name) {
                return $schema;
            }
        }

        throw new InvalidSchema("Invalid schema specified: $name");
    }

    /**
     * @param Schema ...$schemas
     */
    public function setSchemas(Schema ...$schemas): void
    {
        array_walk($schemas, function (Schema $schema) {
            $schema->setDatabase($this);
        });
        $this->schemas = $schemas;
    }

    /**
     * @throws UnknownVersion
     */
    public function getVersion(): string
    {
        if (!$this->variables['version']) {
            throw new UnknownVersion();
        }
        return $this->variables['version'];
    }

    public function isVirtual(): bool
    {
        return false;
    }
}
