<?php

namespace Zan\DoctrineRestBundle\Permissions;

use Zan\DoctrineRestBundle\ORM\ZanQueryBuilder;

class GenericPermissionsCalculator implements PermissionsCalculatorInterface
{
    /** @var string[]  */
    private array $readAbilities = [];

    /** @var string[]  */
    private array $writeAbilities = [];

    public function filterQueryBuilder(ZanQueryBuilder $qb, ActorWithAbilitiesInterface $actor)
    {
        // If the actor has an ability that grants read access return without modifying the query builder
        if ($this->anyAbilityMatchesAnySpecification($actor->getAbilities(), $this->readAbilities)) return;

        // Otherwise, deny all results by default
        $qb->andWhere('0 = 1');
    }

    public function canCreateEntity($entityClassName, $actingUser): bool
    {
        // Allow if the user has write permissions
        return $this->anyAbilityMatchesAnySpecification($actor->getAbilities(), $this->writeAbilities);
    }

    public function canEditEntity($entity, $actingUser): bool
    {
        // Allow if the user has write permissions
        return $this->anyAbilityMatchesAnySpecification($actor->getAbilities(), $this->writeAbilities);
    }

    public function canEditEntityProperty($entity, $property, $actingUser): bool
    {
        // Allow if the user has write permissions
        return $this->anyAbilityMatchesAnySpecification($actor->getAbilities(), $this->writeAbilities);
    }


    protected function anyAbilityMatchesAnySpecification($abilities, $specifications)
    {
        foreach ($abilities as $ability) {
            foreach ($specifications as $specification) {
                if ($this->abilityMatchesSpecification($ability, $specification)) return true;
            }
        }

        return false;
    }

    protected function abilityMatchesSpecification($ability, $specification)
    {
        // '*' means match all abilities
        if ('*' === $specification) return true;

        if ($ability === $specification) return true;

        return false;
    }

    public function setReadAbilities(array $abilities)
    {
        $this->readAbilities = $abilities;
    }

    public function setWriteAbilities(array $abilities)
    {
        $this->writeAbilities = $abilities;
    }
}