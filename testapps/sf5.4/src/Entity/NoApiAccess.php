<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

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
}