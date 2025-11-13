<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\OptionsResolver\OptionsResolver;
/**
 * Formulaire d'import CSV des utilisateurs.
 *
 * Formulaire expose un champ :
 *  - csvFile :
 *      - type : fichier (FileType)
 *      - limité à un poids maximal (par défaut 5 Mo)
 *      - restreint à des fichiers CSV.
 * Téléverser un fichier CSV contenant une liste d'utilisateurs 
 */
final class UsersCsvImportType extends AbstractType
{
    
    /**
     * Taille maximale autorisée pour le fichier en mégaoctets.
    */
    private int $MaxSizeMo=5;
    

    /**
     * Construit le formulaire d'import CSV des utilisateurs.
     *
     * @param FormBuilderInterface $builder Générateur de formulaire.
     * @param array<string, mixed> $options Options du formulaire.
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('csvFile', FileType::class, [
            'label' => 'Importer un fichier CSV',
            'mapped' => false,
            'required' => true,
            'attr' => [
                'accept' => '.csv,text/csv',
            ],
            'constraints' => [
                new File([
                    'maxSize' => $this->MaxSizeMo.'M',
                    'mimeTypes' => [
                        'text/plain', 'text/csv', 'application/csv',
                        'application/vnd.ms-excel', 
                    ],
                    'mimeTypesMessage' => 'Veuillez sélectionner un fichier CSV valide.',
                ]),
            ],
        ]);
    }

    /**
     * Configure les options par défaut du formulaire.
    */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
