<?php

namespace Zan\DoctrineRestBundle\EntityResultSet;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\QueryBuilder;

class ResultSetFilterCollection
{
    /**
     * Tracks ResultSetFilter objects in the collection. By default, filters are ANDed together
     */
    private ArrayCollection $items;

    /**
     * Items in the collection that should be in an "OR" group
     */
    private ArrayCollection $orItems;

    public static function buildFromArray($raw): ResultSetFilterCollection
    {
        $fc = new ResultSetFilterCollection();

        $fc->addAndFiltersFromArray($raw);

        return $fc;
    }

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->orItems = new ArrayCollection();
    }

    public function addAndFilter(ResultSetFilter $filter)
    {
        $this->items->add($filter);
    }

    /**
     * Adds one or more filters that will be ORed with each other and applied after the default ANDed queries
     *
     * @param ResultSetFilter[] $filters
     */
    public function addOrFilters(array $filters)
    {
        $this->orItems->add($filters);
    }

    public function addAndFiltersFromArray(array $raw)
    {
        foreach ($raw as $rawFilter) {
            $this->items->add(ResultSetFilter::buildFromArray($rawFilter));
        }
    }
    
    public function applyToQueryBuilder(QueryBuilder $qb)
    {
        // Apply all standard "and" items
        foreach ($this->getItems() as $filter) {
            $andExpr = $qb->expr()->andX();

            $filter->applyToExpr($andExpr, $qb);

            $qb->andWhere($andExpr);
        }

        // Add in OR groups
        foreach ($this->orItems as $orItemGroup) {
            $ors = $qb->expr()->orX();

            foreach ($orItemGroup as $itemFilter) {
                $orExpr = $qb->expr()->orX();

                $itemFilter->applyToExpr($orExpr, $qb);

                $ors->add($orExpr);
            }

            $qb->andWhere($ors);
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
