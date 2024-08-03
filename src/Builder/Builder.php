<?php

declare(strict_types=1);


namespace Cadfael\Builder;

use Cadfael\Builder\Statement\Account;
use Cadfael\Builder\Statement\Table;
use Cadfael\Engine\Entity\Database;
use Cadfael\Engine\Entity\Schema;
use Cadfael\Engine\Exception\ExistingColumn;
use Cadfael\Engine\Exception\InvalidColumn;
use Cadfael\Engine\Exception\InvalidIndexType;
use Cadfael\Engine\Exception\InvalidTable;
use Cadfael\Engine\Exception\QueryParseException;
use Cadfael\Engine\Exception\UnknownCharacterSet;
use Cadfael\Engine\Exception\UnknownCollation;
use Cadfael\Engine\Exception\UnknownColumnType;
use Cadfael\NullLoggerDefault;
use Psr\Log\LoggerAwareTrait;
use SqlFtw\Parser\InvalidCommand;
use SqlFtw\Parser\Parser;
use SqlFtw\Platform\Platform;
use SqlFtw\Session\Session;
use SqlFtw\Sql\Dal\Set\SetVariablesCommand;
use SqlFtw\Sql\Dal\User\CreateUserCommand;
use SqlFtw\Sql\Ddl\Routine\{CreateFunctionCommand, CreateProcedureCommand, DropFunctionCommand, DropProcedureCommand};
use SqlFtw\Sql\Ddl\Schema\{CreateSchemaCommand, DropSchemaCommand};
use SqlFtw\Sql\Ddl\Table\{AlterTableCommand, CreateTableCommand, DropTableCommand};
use SqlFtw\Sql\Ddl\Trigger\{CreateTriggerCommand, DropTriggerCommand};
use SqlFtw\Sql\Dml\Insert\{InsertSelectCommand, InsertValuesCommand};
use SqlFtw\Sql\Dml\Query\SelectCommand;
use SqlFtw\Sql\Dml\Update\UpdateCommand;
use SqlFtw\Sql\Dml\Utility\{DescribeTableCommand, UseCommand};
use SqlFtw\Sql\Expression\{ObjectIdentifier, QualifiedName, SimpleName};
use SqlFtw\Sql\InvalidDefinitionException;
use SqlFtw\Sql\Statement;
use Throwable;

class Builder
{
    use LoggerAwareTrait, NullLoggerDefault;

    protected string $version;

    protected Database $database;

    /**
     * Container to hold the object representations of the parsed DDL.
     *
     * @var array
     */
    protected array $structures;

    /**
     * @var array<InvalidCommand>
     */
    protected array $errors;

    const DEFAULT_SCHEMA = 'UNKNOWN';

    /**
     * When we build our structure, we need to consider which schema we're dealing with at a specific point.
     * It's possible the script has an inferred schema based on how it's imported, so we'll start from an unknown
     * context.
     *
     * We also need to potentially be able to resolve formulas in places where we normally would expect statements.
     * I suspect collecting a lot of samples of CREATE and ALTER statements will be required.
     *
     * Also need to support generated columns
     *
     * @var string
     */
    protected string $current_schema = self::DEFAULT_SCHEMA;

    public function __construct(string $version)
    {
        $this->version = $version;

        $this->database = new Database();
        $this->database->setVariables([
            'version' => $this->version
        ]);
    }

    /**
     * @param string $statements
     * @return array<Schema>
     * @throws InvalidColumn
     * @throws InvalidDefinitionException
     * @throws QueryParseException
     * @throws UnknownCharacterSet
     * @throws UnknownColumnType
     * @throws Throwable
     */
    public function processIntoSchemas(string $statements): array
    {
        $platform = Platform::get(Platform::MYSQL, $this->version); // version defaults to x.x.99 when no patch number is given
        $session = new Session($platform);
        $parser = new Parser($session);

        // Instantiate initial schema
        $this->getCurrentSchema();

        // Returns a Generator. will not parse anything if you don't iterate over it
        $commands = $parser->parse($statements);

        foreach ($commands as [$statement, $token_list]) {
            /** @var Statement|InvalidColumn $statement */
            // Parser does not throw exceptions. this allows to parse partially invalid code and not fail on first error
            if ($statement instanceof InvalidCommand) {
                //$this->errors[] = $statement->getException()->getMessage();
                throw $statement->getException();
            }

            $this->processStatement($statement);
        }

        // Throw away the default schema if it doesn't exist
        if (count($this->structures['schemas'][self::DEFAULT_SCHEMA]->getTables()) == 0) {
            unset($this->structures['schemas'][self::DEFAULT_SCHEMA]);
        }

        return array_values($this->structures['schemas']);
    }

    /**
     * Create a new schema and link it to the database
     *
     * @param $schema_name
     * @return Schema
     */
    public function getOrCreateDatabase($schema_name): Schema
    {
        if (!isset($this->structures['schemas'][$schema_name])) {
            $schema = new Schema($schema_name);
            $schema->setDatabase($this->database);
            $this->structures['schemas'][$schema_name] = $schema;
        }
        return $this->structures['schemas'][$schema_name];
    }

    /**
     * Fetch the current Schema object we're working with.
     *
     * @return Schema
     */
    protected function getCurrentSchema(): Schema
    {
        return $this->getOrCreateDatabase($this->current_schema);
    }

    public function setCurrentSchema(string $current_schema): void
    {
        $this->current_schema = $current_schema;
    }

    protected function getNameFromIdentifier(ObjectIdentifier $identifier): array
    {
        if ($identifier instanceof QualifiedName) {
            return [
                'schema' => $identifier->getSchema(),
                'table' => $identifier->getName()
            ];
        }

        if ($identifier instanceof SimpleName) {
            return [
                'schema' => $this->current_schema,
                'table' => $identifier->getName()
            ];
        }

        return [];
    }

    /**
     * @param Statement $statement
     * @return void
     * @throws ExistingColumn
     * @throws InvalidColumn
     * @throws InvalidDefinitionException
     * @throws InvalidIndexType
     * @throws InvalidTable
     * @throws QueryParseException
     * @throws UnknownCharacterSet
     * @throws UnknownCollation
     * @throws UnknownColumnType
 */
    protected function processStatement(Statement $statement): void
    {
        $statement_type = get_class($statement);

        switch (true) {
            case $statement instanceof CreateSchemaCommand:
                $this->getOrCreateDatabase($statement->getSchema());
                break;
            case $statement instanceof UseCommand:
                $this->setCurrentSchema($statement->getSchema());
                break;
            case $statement instanceof DropSchemaCommand:
                unset($this->structures['schemas'][$statement->getSchema()]);
                if ($this->current_schema == $statement->getSchema()) {
                    $this->current_schema = self::DEFAULT_SCHEMA;
                }
                break;
            case $statement instanceof DropTableCommand:
                foreach ($statement->getTables() as $table) {
                    $name = $this->getNameFromIdentifier($table);
                    $this->structures['schemas'][$name['schema']]->removeTableByName($name['table']);
                }
                break;
            case $statement instanceof CreateTableCommand:
                $name = $this->getNameFromIdentifier($statement->getTable());
                $table = Table::createFromCommand($statement);
                $this->getOrCreateDatabase($name['schema'])->addTable($table);
                break;
            case $statement instanceof AlterTableCommand:
                $name = $this->getNameFromIdentifier($statement->getTable());
                $table = $this->getOrCreateDatabase($name['schema'])->getTable($name['table']);
                Table::alterFromCommand($statement, $table);
                break;
            case $statement instanceof CreateUserCommand:
                $users = Account::createFromCommand($statement);
                foreach ($users as $user) {
                    $this->database->addAccount($user);
                }
                break;
            case $statement instanceof SetVariablesCommand:
                $this->log()->info("Ignoring SET operation.");
                break;
            case $statement instanceof CreateProcedureCommand:
            case $statement instanceof DropProcedureCommand:
                $this->log()->info("Ignoring PROCEDURE operations.");
                break;
            case $statement instanceof CreateFunctionCommand:
            case $statement instanceof DropFunctionCommand:
                $this->log()->info("Ignoring FUNCTION operations.");
                break;
            case $statement instanceof DropTriggerCommand:
            case $statement instanceof CreateTriggerCommand:
                $this->log()->info("Ignoring TRIGGER operations.");
                break;
            case $statement instanceof DescribeTableCommand:
                $this->log()->info("Ignoring DESCRIBE operation.");
                break;

            case $statement instanceof InsertValuesCommand:
            case $statement instanceof InsertSelectCommand:
            case $statement instanceof UpdateCommand:
            case $statement instanceof SelectCommand:
                $this->log()->info("Ignoring SELECT/INSERT/UPDATE operations.");
                break;
            default:
                $this->log()->info("$statement_type is not supported.");
                throw new QueryParseException("Uncertain on how to handle this statement: $statement_type");
        }
    }
}