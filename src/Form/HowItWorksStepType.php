<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class HowItWorksStepType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('icon', ChoiceType::class, [
                'label' => 'Icône',
                'choices' => [
                    'Compte / inscription' => 'bi-person-plus',
                    'Annonce / mégaphone' => 'bi-megaphone',
                    'Messagerie' => 'bi-chat-dots',
                    'Sécurité / bouclier' => 'bi-shield-check',
                    'Recherche' => 'bi-search',
                    'Photos' => 'bi-images',
                    'Paiement' => 'bi-cash-coin',
                    'Communauté' => 'bi-people',
                    'Éclair / rapidité' => 'bi-lightning-charge',
                    'Coche' => 'bi-check2-circle',
                ],
            ])
            ->add('title', TextType::class, [
                'label' => 'Titre de l’étape',
                'attr' => ['placeholder' => 'Ex. Créez votre compte'],
                'constraints' => [new NotBlank()],
            ])
            ->add('text', TextareaType::class, [
                'label' => 'Description',
                'attr' => ['rows' => 4, 'placeholder' => 'Ce que fait l’utilisateur à cette étape…'],
                'constraints' => [new NotBlank()],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label' => false,
        ]);
    }
}
