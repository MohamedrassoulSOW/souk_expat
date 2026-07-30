<?php

namespace App\Entity;

use App\Repository\AnnonceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[ORM\Entity(repositoryClass: AnnonceRepository::class)]
#[ORM\Table(name: 'annonce')]
#[ORM\UniqueConstraint(name: 'UNIQ_ANNONCE_SLUG', fields: ['slug'])]
class Annonce
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 191)]
    private string $title;

    #[ORM\Column(length: 191, unique: true)]
    private string $slug;

    #[ORM\Column(type: Types::TEXT)]
    private string $description;

    #[ORM\Column]
    private float $price;

    #[ORM\Column(length: 50)]
    private string $status;

    /**
     * Conservé en base pour compatibilité ; non collecté ni affiché sur le site web
     * (contact via messagerie — téléphone réservé aux futures apps mobiles).
     */
    #[ORM\Column(length: 30, options: ['default' => ''])]
    private string $phone = '';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /** Date à laquelle l’admin a accepté l’annonce. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $approvedAt = null;

    // 🔹 Relation vers User, nullable temporairement pour éviter l'erreur setUser()
    #[ORM\ManyToOne(inversedBy: 'annonces')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'annonces')]
    #[ORM\JoinColumn(nullable: false)]
    private Category $category;

    #[ORM\ManyToOne(inversedBy: 'annonces')]
    #[ORM\JoinColumn(nullable: false)]
    private City $city;

    #[ORM\OneToMany(targetEntity: AnnonceImage::class, mappedBy: 'annonce', orphanRemoval: true)]
    private Collection $annonceImages;

    /**
     * @var Collection<int, Thread>
     */
    #[ORM\OneToMany(targetEntity: Thread::class, mappedBy: 'annonce')]
    private Collection $threadsAsAnnonce;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->status = self::STATUS_DRAFT;
        $this->phone = '';
        $this->annonceImages = new ArrayCollection();
        $this->threadsAsAnnonce = new ArrayCollection();
    }

    // ---------- Getters / Setters ----------

    public function getId(): ?int { return $this->id; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self
    {
        $this->title = $title;
        $this->generateSlug();
        return $this;
    }

    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): self { $this->slug = $slug; return $this; }

    public function getDescription(): string { return $this->description; }
    public function setDescription(string $description): self { $this->description = $description; return $this; }

    public function getPrice(): float { return $this->price; }
    public function setPrice(float $price): self { $this->price = $price; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): self
    {
        $this->phone = trim($phone);

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }

    public function getApprovedAt(): ?\DateTimeImmutable { return $this->approvedAt; }

    public function setApprovedAt(?\DateTimeImmutable $approvedAt): self
    {
        $this->approvedAt = $approvedAt;

        return $this;
    }

    // 🔹 Relation User
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getCategory(): Category { return $this->category; }
    public function setCategory(Category $category): self { $this->category = $category; return $this; }

    public function getCity(): City { return $this->city; }
    public function setCity(City $city): self { $this->city = $city; return $this; }

    public function getAnnonceImages(): Collection { return $this->annonceImages; }

    public function addAnnonceImage(AnnonceImage $annonceImage): static
    {
        if (!$this->annonceImages->contains($annonceImage)) {
            $this->annonceImages->add($annonceImage);
            $annonceImage->setAnnonce($this);
        }
        return $this;
    }

    public function removeAnnonceImage(AnnonceImage $annonceImage): static
    {
        if ($this->annonceImages->removeElement($annonceImage)) {
            if ($annonceImage->getAnnonce() === $this) {
                $annonceImage->setAnnonce(null);
            }
        }
        return $this;
    }

    private function generateSlug(): void
    {
        $slugger = new AsciiSlugger();
        $this->slug = strtolower($slugger->slug($this->title));
    }

    /**
     * @return Collection<int, Thread>
     */
    public function getThreadsAsAnnonce(): Collection
    {
        return $this->threadsAsAnnonce;
    }

    public function addThreadsAsAnnonce(Thread $threadsAsAnnonce): static
    {
        if (!$this->threadsAsAnnonce->contains($threadsAsAnnonce)) {
            $this->threadsAsAnnonce->add($threadsAsAnnonce);
            $threadsAsAnnonce->setAnnonce($this);
        }

        return $this;
    }

    public function removeThreadsAsAnnonce(Thread $threadsAsAnnonce): static
    {
        if ($this->threadsAsAnnonce->removeElement($threadsAsAnnonce)) {
            // set the owning side to null (unless already changed)
            if ($threadsAsAnnonce->getAnnonce() === $this) {
                $threadsAsAnnonce->setAnnonce(null);
            }
        }

        return $this;
    }
}
