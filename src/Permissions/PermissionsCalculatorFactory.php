<?php


namespace Zan\DoctrineRestBundle\Permissions;


use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;

class PermissionsCalculatorFactory
{
    /** @var EntityManagerInterface */
    protected $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;

        return $this;
    }

    public function getPermissionsCalculator($entityClassName): ?PermissionsCalculatorInterface
    {
        $calculator = null;
        $this->entityMetadata = $this->em->getClassMetadata($entityClassName);

        $checkClasses = array_merge(
            [ $entityClassName ],
            $this->entityMetadata->parentClasses
        );

        foreach ($checkClasses as $className) {
            // todo: better parsing
            $permissionsClassName = str_replace('Entity', 'EntityApi', $className);
            $permissionsClassName = $permissionsClassName . 'Permissions';
            if (class_exists($permissionsClassName)) {
                $calculator = new $permissionsClassName;
                break;
            }
        }

        return $calculator;
    }
}