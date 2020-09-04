<?php
declare(strict_types=1);

namespace Cadfael\Tests\Engine\Check\Table;

use Cadfael\Engine\Check\Table\PreferredEngine;
use Cadfael\Engine\Entity\Table;
use Cadfael\Engine\Report;
use Cadfael\Tests\Engine\Check\BaseTest;

class PreferredEngineTest extends BaseTest
{
    private Table $myIsamTablePreMySQL5_5;
    private Table $myIsamTablePostMySQL5_5;
    private Table $innoDbTablePostMySQL5_5;

    public function setUp(): void
    {
        $this->myIsamTablePreMySQL5_5 = $this->createEmptyTable([ 'ENGINE' => 'MyISAM' ]);
        $this->myIsamTablePreMySQL5_5->setSchema($this->createSchema([ 'version' => '5.4' ]));

        $this->myIsamTablePostMySQL5_5 = $this->createEmptyTable([ 'ENGINE' => 'MyISAM' ]);
        $this->myIsamTablePostMySQL5_5->setSchema($this->createSchema());

        $this->innoDbTablePostMySQL5_5 = $this->createEmptyTable();
        $this->innoDbTablePostMySQL5_5->setSchema($this->createSchema());
    }

    public function testSupports()
    {
        $check = new PreferredEngine();

        $this->assertFalse(
            $check->supports($this->myIsamTablePreMySQL5_5),
            "Ensure that we dont care about MyISAM tables in a MySQL database version < 5.5."
        );

        $this->assertTrue(
            $check->supports($this->myIsamTablePostMySQL5_5),
            "Ensure that we care about checking MyISAM tables in a MySQL database version >= 5.5."
        );

        $this->assertTrue(
            $check->supports($this->innoDbTablePostMySQL5_5),
            "Ensure that we care about checking InnoDB tables in any MySQL database version."
        );
    }

    public function testRun()
    {
        $check = new PreferredEngine();

        $this->assertEquals(
            Report::STATUS_CONCERN,
            $check->run($this->myIsamTablePostMySQL5_5)->getStatus(),
            "Ensure we report on MyISAM tables in a MySQL database version >= 5.5."
        );

        $this->assertEquals(
            Report::STATUS_OK,
            $check->run($this->innoDbTablePostMySQL5_5)->getStatus(),
            "Ensure we don't care about InnoDB tables in any MySQL database version."
        );
    }
}
