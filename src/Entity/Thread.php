<?php

namespace App\Entity;

use App\Repository\ThreadRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ThreadRepository::class)]
class Thread
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'threadAsBuyer')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $buyer = null;

    #[ORM\ManyToOne(inversedBy: 'threadAsSeller')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $seller = null;

    #[ORM\ManyToOne(inversedBy: 'threadsAsAnnonce')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Annonce $annonce = null;

    /**
     * @var Collection<int, Message>
     */
    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'thread')]
    #[ORM\OrderBy(['createdAt' => 'ASC', 'id' => 'ASC'])]
    private Collection $messagesAsThread;

    public function __construct()
    {
        $this->messagesAsThread = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBuyer(): ?User
    {
        return $this->buyer;
    }

    public function setBuyer(?User $buyer): static
    {
        $this->buyer = $buyer;

        return $this;
    }

    public function getSeller(): ?User
    {
        return $this->seller;
    }

    public function setSeller(?User $seller): static
    {
        $this->seller = $seller;

        return $this;
    }

    public function getAnnonce(): ?Annonce
    {
        return $this->annonce;
    }

    public function setAnnonce(?Annonce $annonce): static
    {
        $this->annonce = $annonce;

        return $this;
    }

    /**
     * @return Collection<int, Message>
     */
    public function getMessagesAsThread(): Collection
    {
        return $this->messagesAsThread;
    }

    public function addMessagesAsThread(Message $messagesAsThread): static
    {
        if (!$this->messagesAsThread->contains($messagesAsThread)) {
            $this->messagesAsThread->add($messagesAsThread);
            $messagesAsThread->setThread($this);
        }

        return $this;
    }

    public function removeMessagesAsThread(Message $messagesAsThread): static
    {
        if ($this->messagesAsThread->removeElement($messagesAsThread)) {
            // set the owning side to null (unless already changed)
            if ($messagesAsThread->getThread() === $this) {
                $messagesAsThread->setThread(null);
            }
        }

        return $this;
    }
}
