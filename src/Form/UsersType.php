<?php

namespace App\Form;

use App\Entity\Users;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

/**
 * UsersType
 * 
 * Formulaire Symfony lié à l'entité Users.
 * Permet de créer et éditer un utilisateur avec les champs nécessaires.
 * 
 * @package App\Form
 */
class UsersType extends AbstractType
{
    /**
     * Construit le formulaire avec les champs de l'entité
     * 
     * @param FormBuilderInterface $builder Le builder du formulaire
     * @param array<string,mixed> $options Options du formulaire (ex: require_password)
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void{
        $requirePassword = $options['require_password'] ?? true;

        $builder
            ->add('user_email', EmailType::class, [
                'required' => false,
                'label' => 'Email',
            ])
            ->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'required' => $requirePassword,
                'label' => 'Mot de passe',
            ])
            ->add('user_role', ChoiceType::class, [
                'label' => 'Rôle',
                'choices' => [
                    'Alternance' => 'alternance',
                    'Internship' => 'internship',
                    'Admin' => 'admin',
                ],
            ])
            // ->add('user_firstconnexion', CheckboxType::class, [
            //     'label' => 'Première connexion',
            //     'required' => false,
            // ])
            ->add('user_firstname', TextType::class, [
                'required' => false,
                'label' => 'Prénom',
            ])
            ->add('user_lastname', TextType::class, [
                'required' => false,
                'label' => 'Nom',
            ])
            ->add('user_level', ChoiceType::class, [
                'required' => false,
                'label' => 'Niveau',
                'choices' => [
                    'B1' => 'B1', 'B2' => 'B2', 'B3' => 'B3',
                    'M1' => 'M1', 'M2' => 'M2',
                ],
            ])
            // ->add('user_lastconnexion', DateType::class, [
            //     'label' => 'Dernière connexion',
            //     'widget' => 'single_text',
            //     'input' => 'datetime_immutable',
            //     'empty_data' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
            //     'required' => true,
            // ])
                ;

        // if ($options['include_submit']) {
        //     $builder->add('save', SubmitType::class, [
        //         'label' => $options['submit_label'] ?? 'Enregistrer', // tu peux l’écraser dans le template
        //     ]);
        // }
    }

    /**
     * Définit les options par défaut du formulaire
     * 
     * @param OptionsResolver $resolver Le résolveur d'options
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver): void{
        $resolver->setDefaults([
            'data_class' => Users::class,
            'require_password' => true,
        ]);
    }
}
