<?php

namespace App\Form;

use App\Entity\Company;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire Symfony lié à l’entité Company.
 *
 * Utilisé dans CompanyController pour créer et éditer une entreprise.
 */
class CompanyType extends AbstractType
{
    /**
     * Construit le formulaire avec les champs de l’entité.
     *
     * @param FormBuilderInterface $builder
     * @param array<string,mixed> $options
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
     * Définit les options par défaut du formulaire.
     *
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Company::class, // Liaison automatique aux propriétés de Company
        ]);
    }
}
