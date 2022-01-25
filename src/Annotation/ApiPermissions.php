<?php


namespace Zan\DoctrineRestBundle\Annotation;


use Doctrine\ORM\Mapping\Annotation;

/**
 * Configures permissions for the annotated entity
 *
 * todo: examples
 * todo: support this at the property/method level?
 *
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({"CLASS"})
 */
class ApiPermissions implements Annotation
{
    private $readSpecification = null;
    private $writeSpecification = null;

    private ?string $permissionsClass;

    public function __construct($read = [], $write = [], string $class = null)
    {
        $this->readSpecification = is_array($read) ? $read : [ $read ];
        $this->writeSpecification = is_array($write) ? $write : [ $write ];

        $this->permissionsClass = $class;
    }

    public function getPermissionsClass()
    {
        return $this->permissionsClass;
    }

    public function getReadAbilities()
    {
        return $this->readSpecification;
    }

    public function getWriteAbilities()
    {
        return $this->writeSpecification;
    }
}