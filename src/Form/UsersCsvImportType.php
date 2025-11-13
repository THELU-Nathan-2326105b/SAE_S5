<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class UsersCsvImportType extends AbstractType
{
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
                    'maxSize' => '5M',
                    'mimeTypes' => [
                        'text/plain', 'text/csv', 'application/csv',
                        'application/vnd.ms-excel', 
                    ],
                    'mimeTypesMessage' => 'Veuillez sélectionner un fichier CSV valide.',
                ]),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
