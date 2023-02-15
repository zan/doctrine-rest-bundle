<?php


namespace Zan\DoctrineRestBundle\Permissions;


use Symfony\Component\Security\Core\User\UserInterface;
use Zan\DoctrineRestBundle\ORM\ZanQueryBuilder;

/**
 * A PermissionsCalculator is a class that defines what actions can be taken on an entity
 * via the REST API.
 */
interface PermissionsCalculatorInterface
{
    /**
     * This method will be called as part of a "list" action.
     *
     * Modify the query builder to filter out any records that $user does not have access to.
     */
    public function filterQueryBuilder(ZanQueryBuilder $qb, ?UserInterface $user): void;

    /**
     * Called when a new entity is being created.
     *
     * $rawData is the plain data posted by the client and will be used to create the entity
     *
     * return false to prevent creating the new entity
     */
    public function canCreateEntity(string $entityClassName, ?UserInterface $user, array $rawData = []): bool;

    /**
     * Called when doing an entity-level check to see if any fields can be edited
     *
     * This is not directly used to control whether an entity can be modified, instead it's used to
     * provide UI hints for whether any field in the entity can be edited.
     *
     * See canEditEntityProperty for the method that controls whether a property can be edited.
     *
     * Return false to indicate that $user cannot edit any properties of this entity
     */
    public function canEditEntity(object $entity, ?UserInterface $user): bool;

    /**
     * Called for each property on an entity that a user is attempting to modify
     *
     * Return false to prevent that property from being edited
     */
    public function canEditEntityProperty(object $entity, string $property, ?UserInterface $user): bool;

    /**
     * Called when a user is attempting to delete an entity
     *
     * Return false to prevent the entity from being deleted.
     */
    public function canDeleteEntity(object $entity, ?UserInterface $user): bool;
}
