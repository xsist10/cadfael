<?php

declare(strict_types=1);

namespace Cadfael\Engine;

use Cadfael\Engine\Exception\InvalidStatus;

class Report
{
    const STATUS_OK        = 1;
    const STATUS_INFO      = 2;
    const STATUS_CONCERN   = 3;
    const STATUS_WARNING   = 4;
    const STATUS_CRITICAL  = 5;

    const STATUS_LABEL = [
        self::STATUS_OK       => 'Ok',
        self::STATUS_INFO     => 'Info',
        self::STATUS_CONCERN  => 'Concern',
        self::STATUS_WARNING  => 'Warning',
        self::STATUS_CRITICAL => 'Critical',
    ];

    protected Check $check;
    protected Entity $entity;
    protected int $status;
    /**
     * @var array<string>
     */
    protected array $messages = [];
    /**
     * @var array<mixed>
     */
    protected array $data     = [];

    /**
     * Report constructor.
     * @param Check $check
     * @param Entity $entity
     * @param int $status
     * @param array<string> $messages
     * @param array<mixed> $data
     * @throws InvalidStatus
     */
    public function __construct(Check $check, Entity $entity, int $status, array $messages = [], array $data = [])
    {
        if (!self::isValidStatus($status)) {
            throw new InvalidStatus("$status is not a valid status code.");
        }

        $this->check = $check;
        $this->entity = $entity;
        $this->status = $status;
        $this->messages = $messages;
        $this->data = $data;
    }

    public static function isValidStatus(int $status): bool
    {
        return $status >= 1 && $status <= 5;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getStatusLabel(): string
    {
        return self::STATUS_LABEL[$this->status];
    }

    public function getCheck(): Check
    {
        return $this->check;
    }

    public function getCheckLabel(): string
    {
        $label = explode('\\', get_class($this->check));
        return array_pop($label) ?? 'unknown';
    }

    public function getEntity(): Entity
    {
        return $this->entity;
    }

    /**
     * @return array<string>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * @return array<mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }
}
