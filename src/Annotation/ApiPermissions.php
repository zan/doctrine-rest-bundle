<?php


namespace Zan\DoctrineRestBundle\Annotation;


use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;
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
#[\Attribute]
class ApiPermissions implements Annotation
{
    private $createSpecification = null;
    private $readSpecification = null;
    private $writeSpecification = null;
    private $deleteSpecification = null;

    private ?string $permissionsClass;

    public function __construct($read = [], $write = [], $create = [], $delete = [], string $class = null)
    {
        $this->createSpecification = is_array($create) ? $create : [ $create ];
        $this->readSpecification = is_array($read) ? $read : [ $read ];
        $this->writeSpecification = is_array($write) ? $write : [ $write ];
        $this->deleteSpecification = is_array($delete) ? $delete : [ $delete ];

        $this->permissionsClass = $class;
    }

    public function getPermissionsClass()
    {
        return $this->permissionsClass;
    }

    public function getCreateAbilities()
    {
        return $this->createSpecification;
    }

    public function getReadAbilities()
    {
        return $this->readSpecification;
    }

    public function getWriteAbilities()
    {
        return $this->writeSpecification;
    }

    public function getDeleteAbilities()
    {
        return $this->deleteSpecification;
    }
}