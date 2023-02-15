<?php


namespace Zan\DoctrineRestBundle\Permissions;


use Symfony\Component\Security\Core\User\UserInterface;
use Zan\DoctrineRestBundle\ORM\ZanQueryBuilder;

class AlwaysAllowsPermissionsCalculator implements PermissionsCalculatorInterface
{
    public function canCreateEntity($entityClassName, $actingUser, $rawData = []): bool
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

    public function filterQueryBuilder(ZanQueryBuilder $qb, $actingUser): void
    {
        $qb->expr()->eq(1, 1);
    }

    public function canDeleteEntity(object $entity, ?UserInterface $user): bool
    {
        return true;
    }
}