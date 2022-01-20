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
        /** @var ApiPermissions $apiPermissionsAnnotation */
        $apiPermissionsAnnotation = ZanAnnotation::getClassAnnotation(
            $this->annotationReader,
            ApiPermissions::class,
            $entityClassName
        );
        if (!$apiPermissionsAnnotation) return null;

        // If there's a permissions class, return it
        // todo: this should work with services
        if ($apiPermissionsAnnotation->getPermissionsClass()) {
            $classNamespace = $apiPermissionsAnnotation->getPermissionsClass();
            return new $classNamespace;
        }

        // Otherwise, build a generic calculator based off information in the annotations
        $calculator = new GenericPermissionsCalculator();
        $calculator->setReadAbilities($apiPermissionsAnnotation->getReadAbilities());
        $calculator->setWriteAbilities($apiPermissionsAnnotation->getWriteAbilities());

        return $calculator;
    }
}