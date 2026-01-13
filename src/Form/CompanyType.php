<?php

namespace App\Form;

use App\Entity\Company;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * CompanyType
 * 
 * Formulaire Symfony lié à l'entité Company.
 * Utilisé dans CompanyController pour créer et éditer une entreprise avec ses informations.
 * 
 * @package App\Form
 */
class CompanyType extends AbstractType
{
    /**
     * Construit le formulaire avec les champs de l'entité.
     *
     * @param FormBuilderInterface $builder Le builder du formulaire
     * @param array<string,mixed> $options Options du formulaire
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('company_name', TextType::class, [
                'label' => 'Nom de l’entreprise',
            ])
            ->add('company_description', TextType::class, [
                'label' => 'Description',
                'required' => false,
            ])
            ->add('company_logo', TextType::class, [
                'label' => 'Logo (chemin ou URL)',
                'required' => false,
            ]);
    }

    /**
     * Définit les options par défaut du formulaire
     *
     * @param OptionsResolver $resolver Le résolveur d'options
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Company::class, // Liaison automatique aux propriétés de Company
        ]);
    }
}
