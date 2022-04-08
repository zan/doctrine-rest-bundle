<?php

namespace Zan\DoctrineRestBundle\EntityResultSet;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\QueryBuilder;

class ResultSetFilterCollection
{
    private $items;

    public static function buildFromArray($raw): ResultSetFilterCollection
    {
        $fc = new ResultSetFilterCollection();

        $fc->addAndFiltersFromArray($raw);

        return $fc;
    }

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    public function addAndFiltersFromArray(array $raw)
    {
        foreach ($raw as $rawFilter) {
            $this->items->add(ResultSetFilter::buildFromArray($rawFilter));
        }
    }
    
    public function applyToQueryBuilder(QueryBuilder $qb)
    {
        foreach ($this->getItems() as $filter) {
            $filter->applyToQueryBuilder($qb);
        }
    }

    /**
     * @return array|ResultSetFilter[]
     */
    public function getItems()
    {
        return $this->items->getValues();
    }
}
