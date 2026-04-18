<?php

namespace App\Form;

use App\Entity\Message;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class MessageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('content', TextareaType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'placeholder' => 'Message',
                    'autocomplete' => 'off',
                    'rows' => 1,
                ],
            ])
            ->add('photo', FileType::class, [
                'label' => false,
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'd-none chat-photo-input',
                    'accept' => 'image/*',
                ],
                'constraints' => [
                    new File(
                        maxSize: '5M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                        mimeTypesMessage: 'Formats acceptés : JPG, PNG, WEBP, GIF.',
                    ),
                ],
            ])
            ->add('latitude', HiddenType::class, [
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'chat-lat-input', 'autocomplete' => 'off'],
            ])
            ->add('longitude', HiddenType::class, [
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'chat-lng-input', 'autocomplete' => 'off'],
            ])
            ->add('locationLabel', HiddenType::class, [
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'chat-location-label-input', 'autocomplete' => 'off'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Message::class,
        ]);
    }
}
