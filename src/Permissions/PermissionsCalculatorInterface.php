<?php


namespace Zan\DoctrineRestBundle\Permissions;


use Doctrine\ORM\QueryBuilder;

interface PermissionsCalculatorInterface
{
    public function canCreateEntity($entityClassName, $actingUser): bool;

    public function canEditEntity($entity, $actingUser): bool;

    public function canEditEntityProperty($entity, $property, $actingUser): bool;

    public function getViewExpr(QueryBuilder $qb, $actingUser);
}