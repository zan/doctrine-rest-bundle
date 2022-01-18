<?php

namespace Zan\DoctrineRestBundle\Util;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Zan\CommonBundle\Util\ZanEntity;

/**
 * Utility methods for working with collections of entities
 */
class ZanDoctrineCollectionUtils
{
    public static function hasItem(Collection $collection, $item)
    {
        foreach ($collection as $currItem) {
            if (ZanEntity::isSame($currItem, $item)) return true;
        }

        return false;
    }

    public static function removeItem(Collection $collection, $item)
    {
        foreach ($collection as $currItem) {
            if (ZanEntity::isSame($currItem, $item)) {
                $collection->removeElement($item);
                break;
            }
        }
    }

    /**
     * Updates the collection so that it only contains $incomingItems
     */
    public static function setItems(Collection $arrayCollection, $setItems)
    {
        $toAdd = [];
        $toRemove = [];

        // Compare incoming items to current items to figure out what should be added
        foreach ($setItems as $incomingEntity) {
            $found = false;

            foreach ($arrayCollection as $currEntity) {
                if (ZanEntity::isSame($incomingEntity, $currEntity)) {
                    $found = true;
                    break;
                }
            }

            // Not found in existing collection, it needs to be added
            if (!$found) $toAdd[] = $incomingEntity;
        }

        // Compare existing items to incoming items to figure out what should be removed
        foreach ($arrayCollection as $currEntity) {
            $found = false;

            foreach ($setItems as $incomingEntity) {
                if (ZanEntity::isSame($currEntity, $incomingEntity)) $found = true;
            }

            // Current entity wasn't found in the incoming items, it's being removed
            if (!$found) $toRemove[] = $currEntity;
        }

        foreach ($toRemove as $entity) {
            $arrayCollection->removeElement($entity);
        }
        foreach ($toAdd as $entity) {
            $arrayCollection->add($entity);
        }
    }
}