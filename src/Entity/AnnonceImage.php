<?php

namespace App\Entity;

use App\Repository\AnnonceImageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AnnonceImageRepository::class)]
class AnnonceImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Nom de fichier legacy (disque public/uploads). Null si stocké en BLOB. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imadeName = null;

    /** Contenu binaire (API mobile / stockage DB). */
    #[ORM\Column(type: Types::BLOB, nullable: true)]
    private $content = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $mimeType = null;

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

    public function setImadeName(?string $imadeName): static
    {
        $this->imadeName = $imadeName;

        return $this;
    }

    public function getContent(): ?string
    {
        if ($this->content === null) {
            return null;
        }

        if (\is_resource($this->content)) {
            $data = stream_get_contents($this->content);
            // Rewind not always possible; Doctrine may re-fetch — cast once
            $this->content = $data === false ? null : $data;

            return $this->content;
        }

        return \is_string($this->content) ? $this->content : null;
    }

    public function setContent(?string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(?string $mimeType): static
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function isStoredInDatabase(): bool
    {
        return $this->getContent() !== null && $this->getContent() !== '';
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
