<?php

namespace App\Form;

use App\Entity\Slider;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\File;

class SliderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre (optionnel, appliqué à tous les médias)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex : Bienvenue sur SoukExpat',
                ],
            ])
            ->add('mediaFiles', FileType::class, [
                'label' => 'Images et / ou vidéos',
                'mapped' => false,
                'required' => true,
                'multiple' => true,
                'help' => 'Plusieurs fichiers possibles · Images JPG/PNG/WEBP (max 8 Mo) · Vidéos MP4/WEBM (max 40 Mo) · Jusqu’à 12 fichiers',
                'attr' => [
                    'accept' => 'image/jpeg,image/png,image/webp,video/mp4,video/webm',
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new Count(
                        min: 1,
                        max: 12,
                        minMessage: 'Ajoutez au moins un fichier.',
                        maxMessage: 'Maximum {{ limit }} fichiers à la fois.'
                    ),
                    new All([
                        new File(
                            maxSize: '40M',
                            mimeTypes: [
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                                'video/mp4',
                                'video/webm',
                            ],
                            mimeTypesMessage: 'Formats autorisés : JPG, PNG, WEBP, MP4, WEBM.',
                        ),
                    ]),
                ],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Activer ces slides',
                'required' => false,
                'data' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Slider::class,
        ]);
    }
}
