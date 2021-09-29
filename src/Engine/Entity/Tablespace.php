<?php

declare(strict_types = 1);

namespace Cadfael\Engine\Entity;

use Cadfael\Engine\Entity;

class Tablespace implements Entity
{
    /**
     * @var int
     */
    public int $id;

    /**
     * @var string
     */
    public string $name;
    public int $flag;
    public string $row_format;
    public int $page_size;
    public int $zip_page_size;
    public string $space_type;
    public int $fs_block_size;
    public int $file_size;
    public int $allocated_size;
    public int $autoextend_size;
    public string $server_version;
    public int $space_version;
    public bool $encryption;
    public string $state;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * @param array<string> $schema This is a raw record from information_schema.TABLE
     * @return Tablespace
     */
    public static function createFromInformationSchema(array $schema): Tablespace
    {
        $table = new Tablespace((string)$schema['NAME']);
        $table->id = (int)$schema['SPACE'];
        $table->flag = (int)$schema['FLAG'];
        $table->row_format = (string)$schema['ROW_FORMAT'];
        $table->page_size = (int)$schema['PAGE_SIZE'];
        $table->zip_page_size = (int)$schema['ZIP_PAGE_SIZE'];
        $table->space_type = (string)$schema['SPACE_TYPE'];
        $table->fs_block_size = (int)$schema['FS_BLOCK_SIZE'];
        $table->file_size = (int)$schema['FILE_SIZE'];
        $table->allocated_size = (int)$schema['ALLOCATED_SIZE'];
        $table->autoextend_size = (int)$schema['AUTOEXTEND_SIZE'];
        $table->server_version = (string)$schema['SERVER_VERSION'];
        $table->space_version = (int)$schema['SPACE_VERSION'];
        $table->encryption = (bool)$schema['ENCRYPTION'];
        $table->state = (string)$schema['STATE'];

        return $table;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isVirtual(): bool
    {
        return true;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->getName();
    }
}
