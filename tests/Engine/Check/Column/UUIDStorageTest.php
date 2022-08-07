<?php

declare(strict_types = 1);

namespace Cadfael\Tests\Engine\Check\Column;

use Cadfael\Engine\Report;
use Cadfael\Tests\Engine\BaseTest;
use Cadfael\Tests\Engine\Check\ColumnBuilder;
use Cadfael\Engine\Check\Column\UUIDStorage;

class UUIDStorageTest extends BaseTest
{
    protected $non_uuid_string_column;
    protected $non_uuid_binary_column;
    protected $uuid_binary_column;
    protected $uuid_string_column;

    public function setUp(): void
    {
        $builder = new ColumnBuilder();
        $this->non_uuid_string_column = $builder->name("generic_string_column")
            ->varchar(36)
            ->generate();
        $this->non_uuid_string_column->setTable($this->createTable());

        $this->non_uuid_binary_column = $builder->name("generic_binary_column")
            ->binary(16)
            ->generate();
        $this->non_uuid_binary_column->setTable($this->createTable());

        $this->uuid_string_column = $builder->name("uuid_string_column")
            ->varchar(36)
            ->generate();
        $this->uuid_string_column->setTable($this->createTable());

        $this->uuid_binary_column = $builder->name("uuid_binary_column")
            ->binary(16)
            ->generate();
        $this->uuid_binary_column->setTable($this->createTable());
    }

    public function testSupports()
    {
        $check = new UUIDStorage();

        $this->assertTrue($check->supports($this->non_uuid_string_column));
        $this->assertTrue($check->supports($this->non_uuid_binary_column));
        $this->assertTrue($check->supports($this->uuid_string_column));
        $this->assertTrue($check->supports($this->uuid_binary_column));
    }

    public function testRun()
    {
        $check = new UUIDStorage();
        $this->assertNull($check->run($this->non_uuid_string_column));
        $this->assertNull($check->run($this->non_uuid_binary_column));
        $this->assertEquals(Report::STATUS_CONCERN, $check->run($this->uuid_string_column)->getStatus());
        $this->assertEquals(Report::STATUS_OK, $check->run($this->uuid_binary_column)->getStatus());
    }
}
