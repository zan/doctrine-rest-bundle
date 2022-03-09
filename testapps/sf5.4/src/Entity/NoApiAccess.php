<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Zan\DoctrineRestBundle\Annotation\PublicId;

/**
 * This entity exists for application testing
 *
 * @ORM\Entity
 * @ORM\Table(name="test_noApiAccess")
 */
class NoApiAccess
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id;

    /**
     * Human-readable unique ID
     *
     * @ORM\Column(name="publicId", type="string", length=255, nullable=true)
     *
     * @PublicId
     */
    private ?string $publicId = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPublicId(): ?string
    {
        return $this->publicId;
    }

    public function setPublicId(?string $publicId): void
    {
        $this->publicId = $publicId;
    }
}