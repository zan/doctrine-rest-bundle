<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Zan\DoctrineRestBundle\Annotation\ApiEnabled;

/**
 * @ORM\Entity
 * @ORM\Table(name="groups")
 */
class Group
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
    private string $label;

    /**
     * @var UserGroup[]
     * @ORM\OneToMany(targetEntity="UserGroup", mappedBy="group")
     *
     * @ApiEnabled
     */
    protected $groupUserMappings;

    /**
     * The user who manages this group
     *
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="managerId", referencedColumnName="id")
     */
    private ?User $manager;

    public function __construct(string $label)
    {
        $this->label = $label;

        $this->groupUserMappings = new ArrayCollection();
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getManager(): ?User
    {
        return $this->manager;
    }

    public function setManager(?User $manager): void
    {
        $this->manager = $manager;
    }
}