<?php


namespace Zan\DoctrineRestBundle\Permissions;


use Doctrine\ORM\QueryBuilder;
use Zan\DoctrineRestBundle\ORM\ZanQueryBuilder;

/**
 * todo: docs
 */
interface PermissionsCalculatorInterface
{
    public function filterQueryBuilder(ZanQueryBuilder $qb, ActorWithAbilitiesInterface $actor): void;

    public function canCreateEntity(string $entityClassName, ActorWithAbilitiesInterface $actor, array $rawData = []): bool;

    public function canEditEntity(object $entity, ActorWithAbilitiesInterface $actor): bool;

    public function canEditEntityProperty(object $entity, string $property, ActorWithAbilitiesInterface $actor): bool;
}
