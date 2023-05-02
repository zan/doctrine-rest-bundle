<?php

namespace Zan\DoctrineRestBundle\GeneratedPublicId;

use Zan\DoctrineRestBundle\Entity\GeneratedPublicIdStateEntry;

/**
 * Indicates that this entity supports automatic publicId generation
 *
 * Apply this to your entities with:
 *
 *      ORM\EntityListeners({
 *       "Zan\DoctrineRestBundle\GeneratedPublicId\GeneratedPublicIdListener"
 *      })
 *
 * See also: GeneratedPublicIdListener
 */
interface GeneratedPublicIdInterface
{
    /**
     * Returns a string that uniquely identifies the type of entity that the public ID
     * is being generated for.
     *
     * In many cases this can be the class namespace, but for certain dynamic entities
     * this may need to return something more complicated.
     *
     * Example implementation:
     *
     *     return get_class($this);
     */
    public function getPublicIdStateKey(): string;

    /**
     * Returns the current publicId for this entity
     *
     * NOTE: Automatic publicId generation via GeneratedPublicIdListener will only happen if this returns null initially
     *
     * @return mixed
     */
    public function getPublicId();

    /**
     * The entity must use the current GeneratedPublicIdStateEntry to generate a new publicId and then update
     * $publicIdState for the next entity that needs it
     */
    public function applyGeneratedPublicIdState(GeneratedPublicIdStateEntry $publicIdState): void;
}