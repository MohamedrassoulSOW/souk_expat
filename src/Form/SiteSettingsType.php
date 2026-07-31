<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\SiteSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

class SiteSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('siteName', TextType::class, [
                'label' => 'Nom du site',
                'help' => 'Affiché dans le footer, les e-mails et les titres de pages.',
                'attr' => ['placeholder' => 'SoukExpat'],
                'constraints' => [new NotBlank()],
            ])
            ->add('tagline', TextType::class, [
                'label' => 'Slogan',
                'help' => 'Phrase courte sous le nom / dans les balises du logo.',
                'attr' => ['placeholder' => 'Le Marché Mondial des Expatriés'],
                'constraints' => [new NotBlank()],
            ])
            ->add('heroTitle', TextType::class, [
                'label' => 'Grand titre (page d’accueil)',
                'help' => 'Texte principal sur le bandeau / slider en haut de l’accueil.',
                'attr' => ['placeholder' => 'Trouvez tout, partout.'],
                'constraints' => [new NotBlank()],
            ])
            ->add('heroSubtitle', TextType::class, [
                'label' => 'Sous-titre (page d’accueil)',
                'help' => 'Ligne juste sous le grand titre du bandeau.',
                'attr' => ['placeholder' => 'Achetez et vendez entre expatriés au Maroc'],
                'constraints' => [new NotBlank()],
            ])
            ->add('footerText', TextareaType::class, [
                'label' => 'Texte du pied de page',
                'help' => 'Paragraphe sous le logo, en bas de toutes les pages publiques.',
                'attr' => ['rows' => 5],
                'constraints' => [new NotBlank()],
            ])
            ->add('newsletterText', TextType::class, [
                'label' => 'Texte bloc « Restez informé »',
                'help' => 'Colonne contact du footer (le bouton renvoie vers la page Contact).',
                'constraints' => [new NotBlank()],
            ])
            ->add('contactEmail', EmailType::class, [
                'label' => 'E-mail de contact',
                'help' => 'Affiché sur Contact & Mentions. Sert aussi d’expéditeur pour les e-mails automatiques.',
                'attr' => ['placeholder' => 'contact@soukexpat.com'],
                'constraints' => [new NotBlank(), new Email()],
            ])
            ->add('contactPhone', TextType::class, [
                'label' => 'Téléphone (non affiché sur le site)',
                'help' => 'Conservé en base pour usage interne éventuel. Aucun numéro n’est publié sur le site web (contact via messagerie / e-mail uniquement).',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Non publié sur le site',
                    'class' => 'form-control',
                ],
                'row_attr' => ['class' => 'd-none'],
            ])
            ->add('contactAddress', TextType::class, [
                'label' => 'Adresse',
                'help' => 'Page Contact et Mentions légales (siège).',
                'constraints' => [new NotBlank()],
            ])
            ->add('contactHours', TextType::class, [
                'label' => 'Horaires / disponibilité',
                'help' => 'Page Contact (ex. Lun – Ven, 9h – 18h).',
                'constraints' => [new NotBlank()],
            ])
            ->add('facebookUrl', TextType::class, [
                'label' => 'Lien Facebook',
                'help' => 'Laisser vide pour masquer l’icône.',
                'required' => false,
                'attr' => ['placeholder' => 'https://facebook.com/votre-page'],
            ])
            ->add('instagramUrl', TextType::class, [
                'label' => 'Lien Instagram',
                'help' => 'Laisser vide pour masquer l’icône.',
                'required' => false,
                'attr' => ['placeholder' => 'https://instagram.com/votre-compte'],
            ])
            ->add('linkedinUrl', TextType::class, [
                'label' => 'Lien LinkedIn',
                'help' => 'Laisser vide pour masquer l’icône.',
                'required' => false,
                'attr' => ['placeholder' => 'https://linkedin.com/company/…'],
            ])
            ->add('aboutHeading', TextType::class, [
                'label' => 'Titre de la page',
                'help' => 'Page « À propos » — grand titre.',
                'constraints' => [new NotBlank()],
            ])
            ->add('aboutLead', TextType::class, [
                'label' => 'Chapeau (sous le titre)',
                'help' => 'Courte phrase d’intro sous le titre.',
                'constraints' => [new NotBlank()],
            ])
            ->add('aboutBody', TextareaType::class, [
                'label' => 'Texte principal',
                'help' => 'Paragraphe à côté de l’image.',
                'attr' => ['rows' => 7],
                'constraints' => [new NotBlank()],
            ])
            ->add('aboutValue1Title', TextType::class, [
                'label' => 'Carte 1 — titre',
                'help' => 'Première des 3 cartes sous le texte (ex. Sécurité).',
            ])
            ->add('aboutValue1Text', TextareaType::class, [
                'label' => 'Carte 1 — texte',
                'attr' => ['rows' => 4],
            ])
            ->add('aboutValue2Title', TextType::class, [
                'label' => 'Carte 2 — titre',
            ])
            ->add('aboutValue2Text', TextareaType::class, [
                'label' => 'Carte 2 — texte',
                'attr' => ['rows' => 4],
            ])
            ->add('aboutValue3Title', TextType::class, [
                'label' => 'Carte 3 — titre',
            ])
            ->add('aboutValue3Text', TextareaType::class, [
                'label' => 'Carte 3 — texte',
                'attr' => ['rows' => 4],
            ])
            ->add('howItWorksLead', TextType::class, [
                'label' => 'Introduction',
                'help' => 'Phrase sous le titre de la page « Comment ça marche ».',
            ])
            ->add('howItWorksSteps', CollectionType::class, [
                'label' => 'Étapes',
                'entry_type' => HowItWorksStepType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'prototype_name' => '__step__',
                'entry_options' => ['label' => false],
                'attr' => ['class' => 'admin-collection', 'data-collection' => 'steps'],
            ])
            ->add('faqLead', TextType::class, [
                'label' => 'Introduction',
                'help' => 'Phrase sous le titre de la page FAQ.',
            ])
            ->add('faqItems', CollectionType::class, [
                'label' => 'Questions / réponses',
                'entry_type' => FaqItemType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'prototype_name' => '__faq__',
                'entry_options' => ['label' => false],
                'attr' => ['class' => 'admin-collection', 'data-collection' => 'faq'],
            ])
            ->add('legalPublisher', TextareaType::class, [
                'label' => 'Bloc « Éditeur du site »',
                'help' => 'Mentions légales — qui édite le site.',
                'attr' => ['rows' => 6],
            ])
            ->add('legalHosting', TextareaType::class, [
                'label' => 'Bloc « Hébergement »',
                'help' => 'Mentions légales — hébergeur / infrastructure.',
                'attr' => ['rows' => 6],
            ])
            ->add('legalExtra', TextareaType::class, [
                'label' => 'Texte complémentaire (optionnel)',
                'help' => 'Affiché uniquement s’il est rempli (informations en plus).',
                'required' => false,
                'attr' => ['rows' => 5],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SiteSettings::class,
        ]);
    }
}
