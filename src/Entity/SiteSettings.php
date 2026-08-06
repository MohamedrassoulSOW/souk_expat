<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SiteSettingsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SiteSettingsRepository::class)]
#[ORM\Table(name: 'site_settings')]
class SiteSettings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $siteName = 'SoukExpat';

    #[ORM\Column(length: 255)]
    private string $tagline = 'Le Marché Mondial des Expatriés';

    #[ORM\Column(type: Types::TEXT)]
    private string $footerText = 'Le Marché Mondial des Expatriés. Achetez, vendez et échangez en toute confiance au sein de votre communauté au Maroc.';

    #[ORM\Column(length: 255)]
    private string $newsletterText = 'Recevez les meilleures pépites du Souk chaque semaine.';

    #[ORM\Column(length: 180)]
    private string $heroTitle = 'Trouvez tout, partout.';

    #[ORM\Column(length: 255)]
    private string $heroSubtitle = 'Achetez et vendez entre expatriés au Maroc';

    #[ORM\Column(length: 180)]
    private string $contactEmail = 'contact@soukexpat.com';

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $contactPhone = null;

    #[ORM\Column(length: 255)]
    private string $contactAddress = 'Anfa Place, 20250 Casablanca, Maroc';

    #[ORM\Column(length: 120)]
    private string $contactHours = 'Lun – Ven, 9h – 18h (Maroc)';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $facebookUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $instagramUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $linkedinUrl = null;

    #[ORM\Column(length: 180)]
    private string $aboutHeading = 'Bienvenue sur SoukExpat';

    #[ORM\Column(length: 255)]
    private string $aboutLead = 'La passerelle entre les expatriés et les meilleures opportunités locales au Maroc.';

    #[ORM\Column(type: Types::TEXT)]
    private string $aboutBody = 'SoukExpat est né d’une idée simple : faciliter la vie des expatriés en créant un espace sécurisé pour acheter, vendre et échanger des biens. Que vous cherchiez un appartement, un véhicule ou des services de proximité, nous sommes là pour vous.';

    #[ORM\Column(length: 80)]
    private string $aboutValue1Title = 'Sécurité';

    #[ORM\Column(type: Types::TEXT)]
    private string $aboutValue1Text = 'Les annonces sont modérées par notre équipe avant publication pour protéger la communauté.';

    #[ORM\Column(length: 80)]
    private string $aboutValue2Title = 'Communauté';

    #[ORM\Column(type: Types::TEXT)]
    private string $aboutValue2Text = 'Un réseau d’expatriés qui partagent les mêmes besoins et la même envie d’entraide.';

    #[ORM\Column(length: 80)]
    private string $aboutValue3Title = 'Rapidité';

    #[ORM\Column(type: Types::TEXT)]
    private string $aboutValue3Text = 'Une interface fluide pour trouver ou proposer ce dont vous avez besoin en quelques clics.';

    #[ORM\Column(length: 255)]
    private string $howItWorksLead = 'Quatre étapes simples pour vendre ou acheter entre expatriés, en toute confiance sur SoukExpat.';

    /** @var list<array{icon?: string, title: string, text: string}>|null */
    #[ORM\Column(type: Types::JSON)]
    private array $howItWorksSteps = [];

    #[ORM\Column(length: 255)]
    private string $faqLead = 'Les réponses aux demandes les plus courantes sur le fonctionnement de la plateforme.';

    /** @var list<array{q: string, a: string}>|null */
    #[ORM\Column(type: Types::JSON)]
    private array $faqItems = [];

    #[ORM\Column(type: Types::TEXT)]
    private string $legalPublisher = 'Le site SoukExpat (soukexpat.com) est édité dans le cadre d’un projet communautaire dédié aux expatriés au Maroc.';

    #[ORM\Column(type: Types::TEXT)]
    private string $legalHosting = 'Hébergement du site et des contenus conformément à l’infrastructure technique en place. Les données de sous-traitants peuvent être tenues à jour sur simple demande.';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $legalExtra = null;

    #[ORM\Column]
    private bool $purgeMessagesEnabled = true;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
        $this->howItWorksSteps = self::defaultHowItWorksSteps();
        $this->faqItems = self::defaultFaqItems();
    }

    /**
     * @return list<array{icon: string, title: string, text: string}>
     */
    public static function defaultHowItWorksSteps(): array
    {
        return [
            ['icon' => 'bi-person-plus', 'title' => 'Créez votre compte', 'text' => 'Inscrivez-vous gratuitement avec votre e-mail. Complétez votre profil pour rassurer les autres membres.'],
            ['icon' => 'bi-megaphone', 'title' => 'Publiez ou parcourez', 'text' => 'Déposez une annonce avec photos et description claire, ou utilisez les filtres pour trouver ce qu’il vous faut.'],
            ['icon' => 'bi-chat-dots', 'title' => 'Échangez en direct', 'text' => 'Contactez le vendeur via la messagerie intégrée : questions, photos, partage de position.'],
            ['icon' => 'bi-shield-check', 'title' => 'Concluez sereinement', 'text' => 'Rencontrez-vous, vérifiez le bien et le paiement. Les annonces passent par une modération.'],
        ];
    }

    /**
     * @return list<array{q: string, a: string}>
     */
    public static function defaultFaqItems(): array
    {
        return [
            ['q' => 'SoukExpat est-il payant ?', 'a' => 'La création de compte et la consultation des annonces sont gratuites.'],
            ['q' => 'Combien de temps une annonce reste-t-elle en ligne ?', 'a' => 'Les annonces validées restent visibles jusqu’à ce que vous les retiriez ou jusqu’à suppression pour non-respect des conditions.'],
            ['q' => 'Pourquoi mon annonce est-elle « en attente » ?', 'a' => 'Chaque annonce peut être vérifiée par l’équipe avant publication. Vous recevrez une notification lorsqu’elle sera traitée.'],
            ['q' => 'Comment contacter un vendeur ?', 'a' => 'Sur la page de l’annonce, utilisez le bouton pour ouvrir une conversation via la messagerie SoukExpat.'],
            ['q' => 'SoukExpat gère-t-il les paiements ou la livraison ?', 'a' => 'Non : nous mettons en relation acheteurs et vendeurs. Le paiement et la remise restent entre vous.'],
            ['q' => 'Comment signaler une annonce douteuse ?', 'a' => 'Utilisez le formulaire de contact en précisant le lien ou l’identifiant de l’annonce.'],
            ['q' => 'Puis-je modifier ou supprimer mon annonce ?', 'a' => 'Oui : dans « Mes annonces », choisissez modifier ou supprimer.'],
            ['q' => 'Mes données personnelles sont-elles protégées ?', 'a' => 'Le site est accessible en HTTPS. Consultez les mentions légales pour le détail des traitements.'],
        ];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSiteName(): string { return $this->siteName; }
    public function setSiteName(string $siteName): self { $this->siteName = $siteName; return $this; }

    public function getTagline(): string { return $this->tagline; }
    public function setTagline(string $tagline): self { $this->tagline = $tagline; return $this; }

    public function getFooterText(): string { return $this->footerText; }
    public function setFooterText(string $footerText): self { $this->footerText = $footerText; return $this; }

    public function getNewsletterText(): string { return $this->newsletterText; }
    public function setNewsletterText(string $newsletterText): self { $this->newsletterText = $newsletterText; return $this; }

    public function getHeroTitle(): string { return $this->heroTitle; }
    public function setHeroTitle(string $heroTitle): self { $this->heroTitle = $heroTitle; return $this; }

    public function getHeroSubtitle(): string { return $this->heroSubtitle; }
    public function setHeroSubtitle(string $heroSubtitle): self { $this->heroSubtitle = $heroSubtitle; return $this; }

    public function getContactEmail(): string { return $this->contactEmail; }
    public function setContactEmail(string $contactEmail): self { $this->contactEmail = $contactEmail; return $this; }

    public function getContactPhone(): ?string { return $this->contactPhone; }
    public function setContactPhone(?string $contactPhone): self { $this->contactPhone = $contactPhone ?: null; return $this; }

    public function getContactAddress(): string { return $this->contactAddress; }
    public function setContactAddress(string $contactAddress): self { $this->contactAddress = $contactAddress; return $this; }

    public function getContactHours(): string { return $this->contactHours; }
    public function setContactHours(string $contactHours): self { $this->contactHours = $contactHours; return $this; }

    public function getFacebookUrl(): ?string { return $this->facebookUrl; }
    public function setFacebookUrl(?string $facebookUrl): self { $this->facebookUrl = $facebookUrl ?: null; return $this; }

    public function getInstagramUrl(): ?string { return $this->instagramUrl; }
    public function setInstagramUrl(?string $instagramUrl): self { $this->instagramUrl = $instagramUrl ?: null; return $this; }

    public function getLinkedinUrl(): ?string { return $this->linkedinUrl; }
    public function setLinkedinUrl(?string $linkedinUrl): self { $this->linkedinUrl = $linkedinUrl ?: null; return $this; }

    public function getAboutHeading(): string { return $this->aboutHeading; }
    public function setAboutHeading(string $aboutHeading): self { $this->aboutHeading = $aboutHeading; return $this; }

    public function getAboutLead(): string { return $this->aboutLead; }
    public function setAboutLead(string $aboutLead): self { $this->aboutLead = $aboutLead; return $this; }

    public function getAboutBody(): string { return $this->aboutBody; }
    public function setAboutBody(string $aboutBody): self { $this->aboutBody = $aboutBody; return $this; }

    public function getAboutValue1Title(): string { return $this->aboutValue1Title; }
    public function setAboutValue1Title(string $v): self { $this->aboutValue1Title = $v; return $this; }
    public function getAboutValue1Text(): string { return $this->aboutValue1Text; }
    public function setAboutValue1Text(string $v): self { $this->aboutValue1Text = $v; return $this; }

    public function getAboutValue2Title(): string { return $this->aboutValue2Title; }
    public function setAboutValue2Title(string $v): self { $this->aboutValue2Title = $v; return $this; }
    public function getAboutValue2Text(): string { return $this->aboutValue2Text; }
    public function setAboutValue2Text(string $v): self { $this->aboutValue2Text = $v; return $this; }

    public function getAboutValue3Title(): string { return $this->aboutValue3Title; }
    public function setAboutValue3Title(string $v): self { $this->aboutValue3Title = $v; return $this; }
    public function getAboutValue3Text(): string { return $this->aboutValue3Text; }
    public function setAboutValue3Text(string $v): self { $this->aboutValue3Text = $v; return $this; }

    public function getHowItWorksLead(): string { return $this->howItWorksLead; }
    public function setHowItWorksLead(string $howItWorksLead): self { $this->howItWorksLead = $howItWorksLead; return $this; }

    /** @return list<array{icon?: string, title: string, text: string}> */
    public function getHowItWorksSteps(): array
    {
        return $this->howItWorksSteps !== [] ? $this->howItWorksSteps : self::defaultHowItWorksSteps();
    }

    /** @param list<array{icon?: string, title: string, text: string}> $howItWorksSteps */
    public function setHowItWorksSteps(array $howItWorksSteps): self
    {
        $this->howItWorksSteps = $howItWorksSteps;
        return $this;
    }

    public function getFaqLead(): string { return $this->faqLead; }
    public function setFaqLead(string $faqLead): self { $this->faqLead = $faqLead; return $this; }

    /** @return list<array{q: string, a: string}> */
    public function getFaqItems(): array
    {
        return $this->faqItems !== [] ? $this->faqItems : self::defaultFaqItems();
    }

    /** @param list<array{q: string, a: string}> $faqItems */
    public function setFaqItems(array $faqItems): self
    {
        $this->faqItems = $faqItems;
        return $this;
    }

    public function getLegalPublisher(): string { return $this->legalPublisher; }
    public function setLegalPublisher(string $legalPublisher): self { $this->legalPublisher = $legalPublisher; return $this; }

    public function getLegalHosting(): string { return $this->legalHosting; }
    public function setLegalHosting(string $legalHosting): self { $this->legalHosting = $legalHosting; return $this; }

    public function getLegalExtra(): ?string { return $this->legalExtra; }
    public function setLegalExtra(?string $legalExtra): self { $this->legalExtra = $legalExtra ?: null; return $this; }

    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function touch(): self { $this->updatedAt = new \DateTimeImmutable(); return $this; }

    public function isPurgeMessagesEnabled(): bool { return $this->purgeMessagesEnabled; }
    public function setPurgeMessagesEnabled(bool $enabled): self { $this->purgeMessagesEnabled = $enabled; return $this; }
}
