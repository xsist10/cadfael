<?php
declare(strict_types=1);

namespace Cadfael\Tests\Engine\Check\MySQL\Column;

use Cadfael\Engine\Check\MySQL\Column\CorrectUtf8Encoding;
use Cadfael\Engine\Report;
use Cadfael\Tests\Engine\Check\MySQL\BaseTest;
use Cadfael\Tests\Engine\Check\MySQL\ColumnBuilder;

class CorrectUtf8EncodingTest extends BaseTest
{
    protected $nonCharacterColumn;
    protected $correctCharacterEncodingColumn;
    protected $incorrectCharacterEncodingColumn;

    public function setUp(): void
    {
        $builder = new ColumnBuilder();
        $this->nonCharacterColumn = $builder->name("nonCharacterColumn")
            ->int()
            ->generate();
        $this->nonCharacterColumn->setTable($this->createTable());

        $this->correctCharacterEncodingColumn = $builder->name("correctCharacterEncodingColumn")
            ->varchar()
            ->character_encoding('utf8mb4')
            ->generate();
        $this->correctCharacterEncodingColumn->setTable($this->createTable());

        $this->incorrectCharacterEncodingColumn = $builder->name("incorrectCharacterEncodingColumn")
            ->varchar()
            ->character_encoding('utf8')
            ->generate();
        $this->incorrectCharacterEncodingColumn->setTable($this->createTable());
    }

    public function testSupports()
    {
        $check = new CorrectUtf8Encoding();

        $this->assertTrue($check->supports($this->nonCharacterColumn));
        $this->assertTrue($check->supports($this->correctCharacterEncodingColumn));
        $this->assertTrue($check->supports($this->incorrectCharacterEncodingColumn));
    }

    public function testRun()
    {
        $check = new CorrectUtf8Encoding();
        $this->assertNull($check->run($this->nonCharacterColumn));
        $this->assertEquals(Report::STATUS_OK, $check->run($this->correctCharacterEncodingColumn)->getStatus());
        $this->assertEquals(Report::STATUS_CONCERN, $check->run($this->incorrectCharacterEncodingColumn)->getStatus());
    }
}
