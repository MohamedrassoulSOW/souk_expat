<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Entity\Contact;

class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Votre Nom',
                'attr' => ['placeholder' => 'Moussa Ly', 'class' => 'form-control-lg bg-light border-0 shadow-none']
            ])
            ->add('email', EmailType::class, [
                'label' => 'Votre Email',
                'attr' => ['placeholder' => 'moussa@example.com', 'class' => 'form-control-lg bg-light border-0 shadow-none']
            ])
            ->add('subject', ChoiceType::class, [
                'label' => 'Sujet',
                'placeholder' => 'Choisir un sujet',
                'choices' => [
                    'Aide sur une annonce' => 'Aide sur une annonce',
                    'Signaler un contenu' => 'signalement',
                    'Partenariat' => 'partenariat',
                    'Autre' => 'autre',
                ],
                'required' => true,
                'attr' => ['class' => 'form-select-lg bg-light border-0 shadow-none'],
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Message',
                'attr' => ['rows' => 5, 'placeholder' => 'Comment pouvons-nous vous aider ?', 'class' => 'bg-light border-0 shadow-none']
            ])
            // Honeypot anti-spam (doit rester vide)
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
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Contact::class,
        ]);
    }
}