<?php

namespace App\Form;

use App\Entity\Annonce;
use App\Entity\Category;
use App\Entity\City;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Image;


class AnnonceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre de l’annonce',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex : Appartement à louer à Dakar'
                ],
            ])

            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 6,
                ],
            ])

            ->add('price', MoneyType::class, [
                'label' => 'Prix',
                'currency' => '',
                'attr' => [
                    'class' => 'form-control',
                ],
            ])

            ->add('phone', null, [
                'label' => 'Numéro de téléphone',
                'attr' => [
                    'placeholder' => 'Ex : 0612345678'
                ]
            ])

            ->add('category', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'name',
                'label' => 'Catégorie',
                'placeholder' => 'Choisir une catégorie',
                'attr' => [
                    'class' => 'form-select',
                ],
            ])

            ->add('city', EntityType::class, [
                'class' => City::class,
                'choice_label' => 'name',
                'label' => 'Ville',
                'placeholder' => 'Choisir une ville',
                'attr' => [
                    'class' => 'form-select',
                ],
            ])

            ->add('images', FileType::class, [
                'label' => 'Photos',
                'mapped' => false,
                'multiple' => true,
                'required' => false,
                'constraints' => [
                    new All([
                        new Image(
                            maxSize: '4M',
                            mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                            mimeTypesMessage: 'Formats autorisés : JPG, PNG, WEBP'
                        )
                    ])
                ],
            ])

        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Annonce::class,
        ]);
    }
}

