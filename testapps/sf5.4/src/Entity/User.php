<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Zan\DoctrineRestBundle\Annotation\ApiEnabled;
use Zan\DoctrineRestBundle\Annotation\ApiPermissions;
use Zan\DoctrineRestBundle\Permissions\ActorWithAbilitiesInterface;

/**
 * @ORM\Table(name="users")
 * @ORM\Entity(repositoryClass=UserRepository::class)
 *
 * @ApiPermissions(read="*", write={"App.editAllData"})
 */
class User implements UserInterface, ActorWithAbilitiesInterface
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     *
     * @ApiEnabled
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=180, unique=true)
     *
     * @ApiEnabled
     */
    private $username;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    private $roles = [];

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
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function getAbilities(): array
    {
        return [];
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

    /**
     * @deprecated since Symfony 5.3, use getUserIdentifier instead
     */
    public function getUsername(): string
    {
        return (string) $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * This method can be removed in Symfony 6.0 - is not needed for apps that do not check user passwords.
     *
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return null;
    }

    /**
     * This method can be removed in Symfony 6.0 - is not needed for apps that do not check user passwords.
     *
     * @see UserInterface
     */
    public function getSalt(): ?string
    {
        return null;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials()
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
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
