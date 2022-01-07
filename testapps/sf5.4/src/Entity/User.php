<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Zan\DoctrineRestBundle\Annotation\ApiEnabled;

/**
 * @ORM\Entity
 * @ORM\Table(name="users")
 */
class User
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
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

    /**
     * @ORM\Column(type="integer", nullable=false)
     */
    private int $numFailedLogins = 0;

    /**
     * @var UserGroup[]
     * @ORM\OneToMany(targetEntity="UserGroup", mappedBy="user")
     *
     * @ApiEnabled
     */
    protected $userGroupMappings;

    /**
     * @ORM\ManyToOne(targetEntity="Group")
     * @ORM\JoinColumn(referencedColumnName="id")
     */
    protected ?Group $defaultGroup;

    public function __construct(string $username)
    {
        $this->username = $username;

        $this->userGroupMappings = new ArrayCollection();
    }

    /**
     * @return Group[]
     */
    public function getGroups()
    {
        $groups = [];
        foreach ($this->userGroupMappings as $userGroup) {
            $groups[] = $userGroup->getGroup();
        }

        return $groups;
    }

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

    public function getNumFailedLogins(): int
    {
        return $this->numFailedLogins;
    }

    public function setNumFailedLogins(int $numFailedLogins): void
    {
        $this->numFailedLogins = $numFailedLogins;
    }

    public function getDefaultGroup(): ?Group
    {
        return $this->defaultGroup;
    }

    public function setDefaultGroup(?Group $defaultGroup): void
    {
        $this->defaultGroup = $defaultGroup;
    }
}