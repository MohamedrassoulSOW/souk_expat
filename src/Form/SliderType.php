<?php

namespace App\Form;

use App\Entity\Slider;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class SliderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre sur la slide',
                'required' => false
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Image du Slider (JPG, PNG)',
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new File(
                        maxSize: '5M', // Utilisation des arguments nommés (pas de tableau [])
                        mimeTypes: ['image/jpeg', 'image/png'],
                        mimeTypesMessage: 'Merci d\'uploader une image valide'
                    )
                ],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Activer cette slide ?',
                'required' => false,
                'data' => true, // Coché par défaut
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Slider::class,
        ]);
    }
}
