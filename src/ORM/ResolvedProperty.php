<?php

namespace Zan\DoctrineRestBundle\ORM;

class ResolvedProperty
{
    private ?string $queryAlias;

    /** @var array|string[]  */
    private array $joinedTables = [];

    public function __construct($queryAlias = null)
    {
        $this->queryAlias = $queryAlias;
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
}