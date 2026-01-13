<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * ResetPasswordRequestFormType
 * 
 * Formulaire de demande de réinitialisation de mot de passe.
 * Utilisé pour capturer l'email de l'utilisateur qui souhaite réinitialiser son mot de passe.
 * 
 * @package App\Form
 */
class ResetPasswordRequestFormType extends AbstractType
{
    /**
     * Construit le formulaire de demande de réinitialisation
     * 
     * @param FormBuilderInterface $builder Le builder du formulaire
     * @param array<string,mixed> $options Options du formulaire
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('user_email', EmailType::class, [
                'attr' => ['autocomplete' => 'email'],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter your email',
                    ]),
                ],
            ])
        ;
    }

    /**
     * Définit les options par défaut du formulaire
     * 
     * @param OptionsResolver $resolver Le résolveur d'options
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
