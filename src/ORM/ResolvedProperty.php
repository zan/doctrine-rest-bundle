<?php

namespace Zan\DoctrineRestBundle\ORM;

use Zan\DoctrineRestBundle\Annotation\Sorter;

class ResolvedProperty
{
    private ?string $queryAlias;

    /** @var array|string[]  */
    private array $joinedTables = [];

    private ?\ReflectionProperty $reflectionProperty;

    /** @var string Doctrine "field type" (string, text, int, etc.) */
    private ?string $fieldType;

    public function __construct($queryAlias = null, \ReflectionProperty $reflectionProperty = null, string $fieldType = null)
    {
        $this->queryAlias = $queryAlias;

        $this->reflectionProperty = $reflectionProperty;
        $this->fieldType = $fieldType;
    }

    /**
     * Returns true if this is a column that contains text and is eligible for natural sorting
     *
     * https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/types.html#string-types
     */
    public function isEligibleForNaturalSort()
    {
        if (!$this->reflectionProperty) throw new \LogicException('ResolvedProperty was not created with a reflectionProperty');

        $sorterAttr = $this->reflectionProperty->getAttributes(Sorter::class);
        if (!$sorterAttr) return false;

        /** @var Sorter $sorter */
        $sorter = $sorterAttr[0]->newInstance();

        return $sorter->getStrategy() === Sorter::STRATEGY_NATURAL;
    }

    public function addJoinAlias(string $alias)
    {
        $this->joinedTables[] = $alias;
    }

    public function getJoinedAliases()
    {
        return $this->joinedTables;
    }

    public function getQueryAlias(): ?string
    {
        return $this->queryAlias;
    }

    public function setQueryAlias(string $alias)
    {
        $this->queryAlias = $alias;
    }

    public function getReflectionProperty(): ?\ReflectionProperty
    {
        return $this->reflectionProperty;
    }

    public function setReflectionProperty(?\ReflectionProperty $reflectionProperty): void
    {
        $this->reflectionProperty = $reflectionProperty;
    }

    public function getFieldType(): ?string
    {
        return $this->fieldType;
    }

    public function setFieldType(?string $fieldType): void
    {
        $this->fieldType = $fieldType;
    }
}