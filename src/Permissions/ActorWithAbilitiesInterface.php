<?php

namespace Zan\DoctrineRestBundle\Permissions;

/**
 * Indicates that this object is aware of "abilities" that grant permissions in certain contexts
 */
interface ActorWithAbilitiesInterface
{
    /**
     * @return array|string[]
     */
    public function getAbilities(): array;
}