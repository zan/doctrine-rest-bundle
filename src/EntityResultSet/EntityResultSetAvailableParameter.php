<?php


namespace Zan\DoctrineRestBundle\EntityResultSet;


class EntityResultSetAvailableParameter
{
    /** @var string */
    protected $name;

    /** @var ?string */
    protected $description;

    /** @var ?mixed */
    protected $default;

    public function __construct(string $name, string $description = '', ?string $default = null)
    {
        $this->name = $name;
        $this->description = $description;
        $this->default = $default;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getDefault()
    {
        return $this->default;
    }

    public function setDefault($default): void
    {
        $this->default = $default;
    }
}