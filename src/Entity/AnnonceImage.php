<?php

namespace App\Entity;

use App\Repository\AnnonceImageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AnnonceImageRepository::class)]
class AnnonceImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $imadeName = null;

    #[ORM\ManyToOne(inversedBy: 'annonceImages')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Annonce $annonce = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getImadeName(): ?string
    {
        return $this->imadeName;
    }

    public function setImadeName(string $imadeName): static
    {
        $this->imadeName = $imadeName;

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
}
