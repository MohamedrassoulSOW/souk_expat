<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;

class AvatarType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('avatar', FileType::class, [
            'mapped' => false,
            'required' => true,
            'constraints' => [
                new File(
                    maxSize: '2M',
                    mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                    mimeTypesMessage: 'Veuillez uploader une image valide (JPG, PNG, WEBP)'
                )
            ],
        ]);
    }
}