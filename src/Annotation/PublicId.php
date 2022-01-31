<?php


namespace Zan\DoctrineRestBundle\Annotation;


use Doctrine\ORM\Mapping\Annotation;

/**
 * Indicates that this field is a human-readable unique ID that is designed for public use
 *
 * This is in contrast to an integer ID used internally by the database
 *
 * @Annotation
 * @Target({"PROPERTY"})
 */
class PublicId implements Annotation
{

}