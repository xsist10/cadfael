<?php

declare(strict_types=1);

namespace Cadfael\Tests\Engine;

use Cadfael\Engine\Entity\Account;
use Cadfael\Engine\Entity\Account\User;
use Cadfael\Engine\Entity\Database;
use Cadfael\Engine\Entity\Query;
use Cadfael\Engine\Entity\Schema;
use Cadfael\Engine\Entity\Table;
use Cadfael\Engine\Entity\Tablespace;
use PHPUnit\Framework\TestCase;

abstract class BaseTest extends TestCase
{
    protected function createDatabase(array $variables = []): Database
    {
        $base_variables = [
            "version" => "5.7"
        ];
        $database = new Database(null);
        $database->setVariables(array_merge($base_variables, $variables));
        $database->setTablespaces($this->createTablespace());
        return $database;
    }

    protected function createSchema(array $variables = []): Schema
    {
        $schema = new Schema('test');
        $schema->setDatabase($this->createDatabase($variables));
        return $schema;
    }

    protected function createQuery(string $digest, Schema $schema): Query
    {
        return new Query($digest, $schema);
    }

    /**
     * Builds a Table instance of an InnoDB table with a couple of rows. This is considered our base table format and
     * any deviations from this will need to be specified in the override parameters.
     *
     * @param array $override
     * @return Table
     */
    protected function createTable(array $override = []): Table
    {
        $base = [
            "TABLE_CATALOG"     => "MOCK_CATALOG",
            "TABLE_SCHEMA"      => "MOCK_SCHEMA",
            "TABLE_NAME"        => "MOCK_TABLE",
            "TABLE_TYPE"        => "BASE TABLE",
            "ENGINE"            => "InnoDB",
            "VERSION"           => "10",
            "ROW_FORMAT"        => "Fixed",
            "TABLE_ROWS"        => 200,
            "AVG_ROW_LENGTH"    => 384,
            "DATA_LENGTH"       => 2311,
            "MAX_DATA_LENGTH"   => 16434816,
            "INDEX_LENGTH"      => 0,
            "DATA_FREE"         => 0,
            "AUTO_INCREMENT"    => 1,
            "CREATE_TIME"       => "2020-05-30 11:29:56",
            "UPDATE_TIME"       => null,
            "CHECK_TIME"        => null,
            "TABLE_COLLATION"   => "utf8_general_ci",
            "CHECKSUM"          => null,
            "CREATE_OPTIONS"    => "",
            "TABLE_COMMENT"     => "",
        ];

        return Table::createFromInformationSchema(array_merge($base, $override));
    }

    protected function createEmptyTable(array $override = []): Table
    {
        return $this->createTable(
            array_merge(
                [
                    "TABLE_ROWS"  => null,
                    "DATA_LENGTH" => 0
                ],
                $override
            )
        );
    }

    protected function createVirtualTable(array $override = []): Table
    {
        return $this->createTable(
            array_merge(
                [
                    "ENGINE" => "BLACKHOLE"
                ],
                $override
            )
        );
    }

    protected function createAccount(string $user, string $host, Database $database = null): Account
    {
        $account = Account::withUser(new User($user, $host, is_fleshed: true));
        if ($database) {
            $account->setDatabase($database);
        }
        return $account;
    }

    protected function createTablespace(string $spaceType = 'General')
    {
        return Tablespace::createFromInformationSchema([
            'SPACE' => '1',
            'NAME' => 'mysql',
            'FLAG' => '18432',
            'ROW_FORMAT' => 'Any',
            'PAGE_SIZE' => '16384',
            'ZIP_PAGE_SIZE' => '0',
            'SPACE_TYPE' => $spaceType,
            'FS_BLOCK_SIZE' => '4096',
            'FILE_SIZE' => '54525952',
            'ALLOCATED_SIZE' => '54530048',
            'AUTOEXTEND_SIZE' => '0',
            'SERVER_VERSION' => '8.0.19',
            'SPACE_VERSION' => '1',
            'ENCRYPTION' => 'N',
            'STATE' => 'normal'
        ]);
    }
}