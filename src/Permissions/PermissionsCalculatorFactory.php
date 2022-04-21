<?php


namespace Zan\DoctrineRestBundle\Permissions;


use Doctrine\Common\Annotations\Reader;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Zan\CommonBundle\Util\ZanAnnotation;
use Zan\DoctrineRestBundle\Annotation\ApiPermissions;

class PermissionsCalculatorFactory
{
    /** @var EntityManagerInterface */
    protected $em;

    /** @var Reader */
    protected $annotationReader;

    private array $cache = [];

    public function __construct(
        EntityManagerInterface $em,
        Reader $annotationReader
    ) {
        $this->em = $em;
        $this->annotationReader = $annotationReader;

        return $this;
    }

    public function getPermissionsCalculator($entityClassName): ?PermissionsCalculatorInterface
    {
        if (is_object($entityClassName)) $entityClassName = get_class($entityClassName);

        if (array_key_exists($entityClassName, $this->cache)) return $this->cache[$entityClassName];

        /** @var ApiPermissions $apiPermissionsDeclaration */
        $apiPermissionsDeclaration = null;

        // Check for an attribute on the entity
        $reflClass = new \ReflectionClass($entityClassName);
        $attributes = $reflClass->getAttributes(ApiPermissions::class);
        if ($attributes) {
            $apiPermissionsDeclaration = $attributes[0]->newInstance();
        }

        // Try annotations next
        if (!$apiPermissionsDeclaration) {
            $apiPermissionsDeclaration = ZanAnnotation::getClassAnnotation(
                $this->annotationReader,
                ApiPermissions::class,
                $entityClassName
            );
            if (!$apiPermissionsDeclaration) {
                $this->cache[$entityClassName] = null;
                return null;
            }
        }


        // If there's a permissions class, return it
        // todo: this should be able to instantiate services
        if ($apiPermissionsDeclaration->getPermissionsClass()) {
            $calculator = new ($apiPermissionsDeclaration->getPermissionsClass());
            $this->cache[$entityClassName] = $calculator;
            return $calculator;
        }

        // Otherwise, build a generic calculator based off information in the annotations
        $calculator = new GenericPermissionsCalculator();
        $calculator->setReadAbilities($apiPermissionsDeclaration->getReadAbilities());
        $calculator->setWriteAbilities($apiPermissionsDeclaration->getWriteAbilities());

        $this->cache[$entityClassName] = $calculator;
        return $calculator;
    }
}