<?php


namespace Zan\DoctrineRestBundle\Annotation;


use Doctrine\ORM\Mapping\Annotation;

/**
 * Indicates that this field is available to the Entity API and can be sent to remote clients
 *
 * If this is on a method, the method will be called and the result will be sent to the client
 *
 * @Annotation
 * @Target({"PROPERTY", "METHOD"})
 *
 * todo: these should move to an "Attribute" namespace
 */
#[\Attribute]
class ApiEnabled implements Annotation
{

}