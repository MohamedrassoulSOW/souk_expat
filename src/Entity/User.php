<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use App\Entity\Annonce;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 191)]
    private ?string $firstName = null;

    #[ORM\Column(length: 191)]
    private ?string $lastName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatar = null;

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): self
    {
        $this->avatar = $avatar;
        return $this;
    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
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

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    //
    #[ORM\Column(type: 'boolean')]
    private bool $isBlocked = false;

    /**
     * @var Collection<int, Annonce>
     */
    #[ORM\OneToMany(targetEntity: Annonce::class, mappedBy: 'user')]
    private Collection $annonces;

    /**
     * @var Collection<int, Thread>
     */
    #[ORM\OneToMany(targetEntity: Thread::class, mappedBy: 'buyer')]
    private Collection $threadAsBuyer;

    /**
     * @var Collection<int, Thread>
     */
    #[ORM\OneToMany(targetEntity: Thread::class, mappedBy: 'seller')]
    private Collection $threadAsSeller;

    /**
     * @var Collection<int, Message>
     */
    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'sender')]
    private Collection $messagesAsSender;

    /**
     * @var Collection<int, Notification>
     */
    #[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'user')]
    private Collection $notifications;

    public function __construct()
    {
        $this->threadAsBuyer = new ArrayCollection();
        $this->threadAsSeller = new ArrayCollection();
        $this->messagesAsSender = new ArrayCollection();
        $this->notifications = new ArrayCollection();
    }


    public function isBlocked(): bool
    {
        return $this->isBlocked;
    }

    public function setIsBlocked(bool $isBlocked): self
    {
        $this->isBlocked = $isBlocked;
        return $this;
    }

    /**
     * @return Collection<int, Annonce>
     */
    public function getAnnonces(): Collection
    {
        return $this->annonces;
    }

    public function addAnnonce(Annonce $annonce): static
    {
        if (!$this->annonces->contains($annonce)) {
            $this->annonces->add($annonce);
            $annonce->setUser($this);
        }

        return $this;
    }

    public function removeAnnonce(Annonce $annonce): static
    {
        if ($this->annonces->removeElement($annonce)) {
            // set the owning side to null (unless already changed)
            if ($annonce->getUser() === $this) {
                $annonce->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Thread>
     */
    public function getThreadAsBuyer(): Collection
    {
        return $this->threadAsBuyer;
    }

    public function addThreadAsBuyer(Thread $threadAsBuyer): static
    {
        if (!$this->threadAsBuyer->contains($threadAsBuyer)) {
            $this->threadAsBuyer->add($threadAsBuyer);
            $threadAsBuyer->setBuyer($this);
        }

        return $this;
    }

    public function removeThreadAsBuyer(Thread $threadAsBuyer): static
    {
        if ($this->threadAsBuyer->removeElement($threadAsBuyer)) {
            // set the owning side to null (unless already changed)
            if ($threadAsBuyer->getBuyer() === $this) {
                $threadAsBuyer->setBuyer(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Thread>
     */
    public function getThreadAsSeller(): Collection
    {
        return $this->threadAsSeller;
    }

    public function addThreadAsSeller(Thread $threadAsSeller): static
    {
        if (!$this->threadAsSeller->contains($threadAsSeller)) {
            $this->threadAsSeller->add($threadAsSeller);
            $threadAsSeller->setSeller($this);
        }

        return $this;
    }

    public function removeThreadAsSeller(Thread $threadAsSeller): static
    {
        if ($this->threadAsSeller->removeElement($threadAsSeller)) {
            // set the owning side to null (unless already changed)
            if ($threadAsSeller->getSeller() === $this) {
                $threadAsSeller->setSeller(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Message>
     */
    public function getMessagesAsSender(): Collection
    {
        return $this->messagesAsSender;
    }

    public function addMessagesAsSender(Message $messagesAsSender): static
    {
        if (!$this->messagesAsSender->contains($messagesAsSender)) {
            $this->messagesAsSender->add($messagesAsSender);
            $messagesAsSender->setSender($this);
        }

        return $this;
    }

    public function removeMessagesAsSender(Message $messagesAsSender): static
    {
        if ($this->messagesAsSender->removeElement($messagesAsSender)) {
            // set the owning side to null (unless already changed)
            if ($messagesAsSender->getSender() === $this) {
                $messagesAsSender->setSender(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Notification>
     */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function addNotification(Notification $notification): static
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications->add($notification);
            $notification->setUser($this);
        }

        return $this;
    }

    public function removeNotification(Notification $notification): static
    {
        if ($this->notifications->removeElement($notification)) {
            // set the owning side to null (unless already changed)
            if ($notification->getUser() === $this) {
                $notification->setUser(null);
            }
        }

        return $this;
    }  

}
