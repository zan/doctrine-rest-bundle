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
            if (!$apiPermissionsDeclaration) return null;
        }


        // If there's a permissions class, return it
        // todo: this should work with services
        if ($apiPermissionsDeclaration->getPermissionsClass()) {
            return new $apiPermissionsDeclaration->getPermissionsClass();
        }

        // Otherwise, build a generic calculator based off information in the annotations
        $calculator = new GenericPermissionsCalculator();
        $calculator->setReadAbilities($apiPermissionsDeclaration->getReadAbilities());
        $calculator->setWriteAbilities($apiPermissionsDeclaration->getWriteAbilities());

        return $calculator;
    }
}