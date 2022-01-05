<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Zan\DoctrineRestBundle\Annotation\ApiEnabled;

/**
 * @ORM\Entity
 * @ORM\Table(name="app_users")
 */
class User
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     *
     * @ApiEnabled
     */
    private ?int $id;

    /**
     * @ORM\Column(type="string", nullable=false)
     */
    private string $username;

    /**
     * @ORM\Column(type="string", nullable=false)
     */
    private ?string $displayName;

    public function __construct(string $username)
    {
        $this->username = $username;
    }

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(?string $displayName): void
    {
        $this->displayName = $displayName;
    }
}