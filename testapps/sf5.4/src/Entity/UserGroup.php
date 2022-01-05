<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Zan\DoctrineRestBundle\Annotation\ApiEnabled;

/**
 * @ORM\Entity
 * @ORM\Table(name="user_groups")
 */
class UserGroup
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * @ApiEnabled
     */
    private ?int $id;

    /**
     * @ORM\ManyToOne(targetEntity="User", inversedBy="userGroupMappings")
     * @ORM\JoinColumn(referencedColumnName="id", onDelete="cascade")
     *
     * @ApiEnabled
     */
    private User $user;

    /**
     * @ORM\ManyToOne(targetEntity="Group", inversedBy="groupUserMappings")
     * @ORM\JoinColumn(referencedColumnName="id", onDelete="cascade")
     *
     * @ApiEnabled
     */
    private Group $group;

    public function __construct(User $user, Group $group)
    {
        $this->user = $user;
        $this->group = $group;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
    }

    public function getGroup(): Group
    {
        return $this->group;
    }

    public function setGroup(Group $group): void
    {
        $this->group = $group;
    }
}