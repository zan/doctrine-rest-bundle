<?php


namespace Zan\DoctrineRestBundle\Permissions;


use Doctrine\ORM\QueryBuilder;

class AlwaysAllowsPermissionsCalculator implements PermissionsCalculatorInterface
{
    public function canCreateEntity($entityClassName, $actingUser): bool
    {
        return true;
    }

    public function canEditEntity($entity, $actingUser): bool
    {
        return true;
    }

    public function canEditEntityProperty($entity, $property, $actingUser): bool
    {
        return true;
    }

    public function getViewExpr(QueryBuilder $qb, $actingUser)
    {
        return $qb->expr()->eq(1, 1);
    }

}