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

    public function __construct(string $label)
    {
        $this->label = $label;

        $this->groupUserMappings = new ArrayCollection();
    }
}