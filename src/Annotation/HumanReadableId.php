<?php


namespace Zan\DoctrineRestBundle\Annotation;


use Doctrine\ORM\Mapping\Annotation;

/**
 * Indicates that this field is a human-readable unique ID
 *
 * This is in contrast to an integer ID used internally by the database
 *
 * todo: this would be better as publicId
 *
 * @Annotation
 * @Target({"PROPERTY"})
 */
class HumanReadableId implements Annotation
{

}