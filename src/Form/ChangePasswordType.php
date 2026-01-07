<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;


class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('oldPassword', PasswordType::class, [
                'label' => 'Mot de passe actuel',
                'mapped' => false,
                'attr' => ['class' => 'form-control-lg bg-light border-0 shadow-none']
            ])
            ->add('newPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'invalid_message' => 'Les deux mots de passe doivent être identiques.',
                'options' => ['attr' => ['class' => 'form-control-lg bg-light border-0 shadow-none']],
                'required' => true,
                'first_options'  => ['label' => 'Nouveau mot de passe'],
                'second_options' => ['label' => 'Confirmez le nouveau mot de passe'],
                'constraints' => [
                    // On passe directement le message en premier argument, ou via le nom de l'argument
                    new NotBlank(message: 'Veuillez entrer un mot de passe'),
                    new Length(
                        min: 8,
                        minMessage: 'Votre mot de passe doit faire au moins {{ limit }} caractères',
                        max: 4096,
                    ),
                ],
            ]);
    }
}