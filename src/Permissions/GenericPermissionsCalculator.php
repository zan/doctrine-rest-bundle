<?php

namespace Zan\DoctrineRestBundle\Permissions;

use Symfony\Component\Security\Core\User\UserInterface;
use Zan\DoctrineRestBundle\ORM\ZanQueryBuilder;

class GenericPermissionsCalculator implements PermissionsCalculatorInterface
{
    /** @var string[]  */
    private array $readRoles = [];

    /** @var string[]  */
    private array $createRoles = [];

    /** @var string[]  */
    private array $writeRoles = [];

    /** @var string[]  */
    private array $deleteRoles = [];

    public function filterQueryBuilder(ZanQueryBuilder $qb, ?UserInterface $user): void
    {
        // If the user has a role that grants read access return without modifying the query builder
        if ($this->anyRoleMatchesAnySpecification($user->getRoles(), $this->readRoles)) return;

        // Otherwise, deny all results by default
        $qb->andWhere('0 = 1');
    }

    public function canCreateEntity(string $entityClassName, ?UserInterface $user, array $rawData = []): bool
    {
        $createRoles = $this->createRoles;

        // If create roles are specified, use those
        if ($createRoles) {
            return $this->anyRoleMatchesAnySpecification($user->getRoles(), $createRoles);
        }
        // Otherwise, fall back to whether $user can write $entity
        else {
            return $this->anyRoleMatchesAnySpecification($user->getRoles(), $this->writeRoles);
        }
    }

    public function canEditEntity(object $entity, ?UserInterface $user): bool
    {
        // Allow if the user has write permissions
        return $this->anyRoleMatchesAnySpecification($user->getRoles(), $this->writeRoles);
    }

    public function canEditEntityProperty(object $entity, string $property, ?UserInterface $user): bool
    {
        // Allow if the user has write permissions
        return $this->anyRoleMatchesAnySpecification($user->getRoles(), $this->writeRoles);
    }

    public function canDeleteEntity(object $entity, ?UserInterface $user): bool
    {
        $deleteRoles = $this->deleteRoles;

        // If delete roles are specified, use those
        if ($deleteRoles) {
            return $this->anyRoleMatchesAnySpecification($user->getRoles(), $this->deleteRoles);
        }
        // Otherwise, fall back to whether $user can edit $entity
        else {
            return $this->canEditEntity($entity, $user);
        }
    }

    protected function anyRoleMatchesAnySpecification($roles, $specifications)
    {
        // First, check if the specifications allow anyone to read the entity
        foreach ($specifications as $specification) {
            if ('*' === $specification) return true;
        }

        foreach ($roles as $role) {
            foreach ($specifications as $specification) {
                if ($this->roleMatchesSpecification($role, $specification)) return true;
            }
        }

        return false;
    }

    protected function roleMatchesSpecification($role, $specification)
    {
        // '*' means match all roles
        if ('*' === $specification) return true;

        if ($role === $specification) return true;

        return false;
    }

    public function setCreateRoles(array $roles)
    {
        $this->createRoles = $roles;
    }

    public function setReadRoles(array $roles)
    {
        $this->readRoles = $roles;
    }

    public function setWriteRoles(array $roles)
    {
        $this->writeRoles = $roles;
    }

    public function setDeleteRoles(array $roles)
    {
        $this->deleteRoles = $roles;
    }
}
