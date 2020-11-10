<?php

declare(strict_types=1);

namespace Cadfael\Engine\Entity;

use Cadfael\Engine\Entity;
use Cadfael\Engine\Entity\Account\NotClosedProperly;

class Account implements Entity
{
    protected string $username;
    protected string $host;

    protected Database $database;

    public ?NotClosedProperly $account_not_closed_properly = null;

    public function __construct(string $username, string $host)
    {
        $this->username = $username;
        $this->host = $host;
    }

    /**
     * @param Database $database
     */
    public function setDatabase(Database $database): void
    {
        $this->database = $database;
    }

    /**
     * @return Database
     */
    public function getDatabase(): Database
    {
        return $this->database;
    }

    /**
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->username;
    }

    /**
     * @codeCoverageIgnore
     * Skip coverage as this is a basic accessor. Remove if the accessor behaviour becomes more complicated.
     */
    public function setAccountNotClosedProperly(NotClosedProperly $account_not_closed_properly): void
    {
        $this->account_not_closed_properly = $account_not_closed_properly;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->username . '@' . $this->host;
    }

    public function isVirtual(): bool
    {
        return false;
    }
}
