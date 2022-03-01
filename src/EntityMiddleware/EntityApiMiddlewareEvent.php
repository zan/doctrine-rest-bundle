<?php

namespace Zan\DoctrineRestBundle\EntityMiddleware;

use Zan\DoctrineRestBundle\Permissions\ActorWithAbilitiesInterface;

class EntityApiMiddlewareEvent
{
    /**
     * @var mixed The entity being operated on
     */
    private $entity;

    /**
     * @var array The raw data sent by the client
     */
    private $rawData;

    /**
     * @var ActorWithAbilitiesInterface The user making with the API request
     */
    private $actor;

    public function __construct(ActorWithAbilitiesInterface $actor, $rawData, $entity)
    {
        $this->actor = $actor;
        $this->rawData = $rawData;
        $this->entity = $entity;
    }

    public function getEntity(): mixed
    {
        return $this->entity;
    }

    public function setEntity(mixed $entity): void
    {
        $this->entity = $entity;
    }

    public function getRawData(): array
    {
        return $this->rawData;
    }

    public function setRawData(array $rawData): void
    {
        $this->rawData = $rawData;
    }

    public function getActor(): ActorWithAbilitiesInterface
    {
        return $this->actor;
    }

    public function setActor(ActorWithAbilitiesInterface $actor): void
    {
        $this->actor = $actor;
    }
}