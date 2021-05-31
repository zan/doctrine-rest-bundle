<?php


namespace Zan\DoctrineRestBundle\Collection;


use Doctrine\Common\Collections\ArrayCollection;
use Zan\CommonBundle\Util\ZanEntity;

/**
 * Extends ArrayCollection to be aware of entities
 *
 * The same entity will only appear once in this collection.
 *
 * Two entities are considered the same if they are exactly the same object
 * or they have the same non-null identifiers
 *
 * This collection should only be used with a single class of entities (or
 * entities that all inherit from the same entity). Primary keys must be
 * unique among all entities.
 *
 * todo: support custom identifiers (ie. not just getId())
 * todo: support composite keys
 * todo: should enforce that only a single type of entity is added to the collection
 */
class UniqueEntityCollection extends ArrayCollection
{
    public function add($element)
    {
        // Early exit if the elemnt is already in the collection
        foreach ($this->getIterator() as $currElem) {
            if (ZanEntity::isSame($currElem, $element)) return true;
        }

        return parent::add($element);
    }

}