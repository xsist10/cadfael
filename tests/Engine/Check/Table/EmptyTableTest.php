<?php
declare(strict_types=1);

use Cadfael\Engine\Check\Table\EmptyTable;
use Cadfael\Engine\Report;
use Cadfael\Tests\Engine\Check\BaseTest;

class EmptyTableTest extends BaseTest
{
    public function providerTableDataForSupports() {
        return [
            [
                $this->createEmptyTable(),
                true
            ],
            [
                $this->createEmptyTable([ "DATA_FREE" => 110 ]),
                true
            ],
            [
                $this->createTable(),
                true
            ],
            [
                $this->createVirtualTable(),
                false
            ],
        ];
    }

    public function providerTableDataForRun() {
        return [
            [
                $this->createEmptyTable(),
                Report::STATUS_WARNING
            ],
            [
                $this->createEmptyTable([ "DATA_FREE" => 110 ]),
                Report::STATUS_INFO
            ],
            [
                $this->createTable(),
                Report::STATUS_OK
            ],
        ];
    }

    /**
     * @dataProvider providerTableDataForSupports
     */
    public function testSupports($table, $result)
    {
        $check = new EmptyTable();
        $this->assertEquals($check->supports($table), $result, "Ensure that the supports for $table returns $result");
    }

    /**
     * @dataProvider providerTableDataForRun
     */
    public function testRun($table, $result)
    {
        $check = new EmptyTable();
        $this->assertEquals($check->run($table)->getStatus(), $result, "Ensure that the run for $table returns status $result");
    }
}
