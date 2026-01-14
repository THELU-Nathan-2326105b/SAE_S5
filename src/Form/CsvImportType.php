<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\OptionsResolver\OptionsResolver;
/**
 * UsersCsvImportType
 * 
 * Formulaire d'import CSV des utilisateurs.
 * Expose un champ de téléversement de fichier CSV limité en taille et type MIME.
 * Utilisé pour l'import en masse d'utilisateurs depuis un fichier CSV.
 * 
 * @package App\Form
 */
final class CsvImportType extends AbstractType
{
    /**
     * Taille maximale autorisée pour le fichier en mégaoctets
     * 
     * @var int
     */
    private int $MaxSizeMo=10;
    

    /**
     * Construit le formulaire d'import CSV des utilisateurs
     *
     * @param FormBuilderInterface $builder Générateur de formulaire
     * @param array<string,mixed> $options Options du formulaire
     * @return void
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
     * Configure les options par défaut du formulaire
     * 
     * @param OptionsResolver $resolver Le résolveur d'options
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
