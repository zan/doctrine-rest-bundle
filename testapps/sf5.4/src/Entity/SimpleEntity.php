<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Zan\DoctrineRestBundle\Annotation\ApiEnabled;
use Zan\DoctrineRestBundle\Annotation\ApiMiddleware;
use Zan\DoctrineRestBundle\Annotation\ApiPermissions;
use Zan\DoctrineRestBundle\Annotation\PublicId;

/**
 * This entity exists for application testing
 *
 * @ORM\Entity
 * @ORM\Table(name="test_simpleEntities")
 *
 * @ApiPermissions(read="*", write="*")
 * @ApiMiddleware("App\EntityApiMiddleware\SimpleEntityMiddleware")
 */
class SimpleEntity
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     *
     * @ApiEnabled
     */
    private ?int $id = null;

    /**
     * Human-readable unique ID
     *
     * @ORM\Column(name="publicId", type="string", length=255, nullable=true)
     *
     * @PublicId
     * @ApiEnabled
     */
    protected ?string $publicId = null;

    /**
     * @ORM\Column(name="label", type="string", length=255, nullable=true)
     * @ApiEnabled
     */
    private ?string $label = null;

    /**
     * Used for testing entity middleware features
     *
     * @ORM\Column(name="middlewareValue", type="string", length=255, nullable=true)
     *
     * @ApiEnabled
     */
    private ?string $middlewareValue = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): void
    {
        $this->label = $label;
    }

    public function getPublicId(): ?string
    {
        return $this->publicId;
    }

    public function setPublicId(?string $publicId): void
    {
        $this->publicId = $publicId;
    }

    public function getMiddlewareValue(): ?string
    {
        return $this->middlewareValue;
    }

    public function setMiddlewareValue(?string $middlewareValue): void
    {
        $this->middlewareValue = $middlewareValue;
    }
}