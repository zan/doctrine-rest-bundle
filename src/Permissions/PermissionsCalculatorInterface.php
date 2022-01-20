<?php


namespace Zan\DoctrineRestBundle\Permissions;


use Doctrine\ORM\QueryBuilder;
use Zan\DoctrineRestBundle\ORM\ZanQueryBuilder;

/**
 * todo: docs
 */
interface PermissionsCalculatorInterface
{
    public function filterQueryBuilder(ZanQueryBuilder $qb, ActorWithAbilitiesInterface $actingUser);

    public function canCreateEntity($entityClassName, $actingUser): bool;

    public function canEditEntity($entity, $actingUser): bool;

    public function canEditEntityProperty($entity, $property, $actingUser): bool;
}