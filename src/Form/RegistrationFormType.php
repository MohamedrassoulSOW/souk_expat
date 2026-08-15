<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\IsTrue;
use App\Security\PasswordConstraints;
use App\Validator\CaptchaChallenge;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', null, [
                'label' => 'Prénom',
                'required' => true,
                'attr' => ['placeholder' => 'Entrez votre prénom'],
            ])
            ->add('lastName', null, [
                'label' => 'Nom',
                'required' => true,
                'attr' => ['placeholder' => 'Entrez votre nom de famille'],
            ])
            ->add('email', null, [
                'label' => 'E-mail',
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'label' => 'J’accepte les conditions d’utilisation',
                'constraints' => [
                    new IsTrue(message: 'Vous devez accepter les conditions d’utilisation.'),
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'label' => 'Mot de passe',
                'attr' => ['autocomplete' => 'new-password'],
                'constraints' => PasswordConstraints::newPassword(),
            ])
            ->add('captcha', TextType::class, [
                'mapped' => false,
                'label' => 'Code de sécurité',
                'attr' => [
                    'autocomplete' => 'off',
                    'inputmode' => 'text',
                    'autocapitalize' => 'characters',
                    'placeholder' => '5 caractères',
                    'maxlength' => 8,
                ],
                'constraints' => [
                    new CaptchaChallenge(),
                ],
            ])
            ->add('website', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => false,
                'attr' => [
                    'autocomplete' => 'off',
                    'tabindex' => '-1',
                    'aria-hidden' => 'true',
                    'class' => 'contact-honeypot',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
