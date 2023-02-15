<?php

namespace Zan\DoctrineRestBundle\EntityMiddleware;

use Symfony\Component\Security\Core\User\UserInterface;

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
     * The user making with the API request
     */
    private ?UserInterface $user;

    public function __construct(?UserInterface $user, $rawData, $entity)
    {
        $this->user = $user;
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

    public function getUser(): ?UserInterface
    {
        return $this->user;
    }

    public function setUser(?UserInterface $user): void
    {
        $this->user = $user;
    }
}