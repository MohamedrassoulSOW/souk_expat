<?php

namespace App\Twig;

use App\Entity\AnnonceImage;
use App\Service\WhatsAppLinkBuilder;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class FormatExtension extends AbstractExtension
{
    public function __construct(
        private readonly WhatsAppLinkBuilder $whatsAppLinkBuilder,
        private readonly Packages $packages,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('cap999', [$this, 'cap999']),
            new TwigFilter('whatsapp_url', [$this, 'whatsappUrl']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('annonce_image_url', [$this, 'annonceImageUrl']),
        ];
    }

    /**
     * Affiche le nombre tel quel sous 999, sinon "+999".
     */
    public function cap999(int|float|string|null $value): string
    {
        $n = (int) $value;

        return $n >= 999 ? '+999' : (string) $n;
    }

    /**
     * Lien WhatsApp (wa.me) à partir d’un numéro (+212…, 00212… ou 06… Maroc).
     */
    public function whatsappUrl(?string $phone, ?string $prefill = null): string
    {
        return $this->whatsAppLinkBuilder->url($phone, $prefill) ?? '#';
    }

    /**
     * URL publique d’une photo d’annonce (disque ou API media BLOB).
     */
    public function annonceImageUrl(?AnnonceImage $image): ?string
    {
        if (!$image) {
            return null;
        }

        $name = $image->getImadeName();
        if ($name !== null && $name !== '') {
            return $this->packages->getUrl('uploads/annonces/' . $name);
        }

        if ($image->getId()) {
            return $this->urlGenerator->generate('api_v1_media_annonce_image', ['id' => $image->getId()]);
        }

        return null;
    }
}
