<?php

namespace App\Service;

use App\Import\Contract\ImporterFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\KernelInterface;

class CsvImportService
{
    private string $projectDir;

    public function __construct(KernelInterface $kernel)
    {
        $this->projectDir = $kernel->getProjectDir();
    }

    public function moveUploadedCsvAndGetPath(UploadedFile $uploaded, string $prefix = 'import_'): string
    {
        $targetDir = $this->projectDir . '/var/tmp';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }
        $filename = $prefix . uniqid('', true) . '.csv';
        return $uploaded->move($targetDir, $filename)->getPathname();
    }

    public function runImport(string $path, ImporterFactory $importerFactory, string $entity): iterable
    {
        $importer = $importerFactory->create($entity);
        return $importer->import($path, $entity);
    }

    public function persistImportedEntities(iterable $items, EntityManagerInterface $em): void
    {
        foreach ($items as $it) {
            $em->persist($it);
        }
        $em->flush();
    }

    public function countItems(iterable $items): int
    {
        if (is_array($items) || $items instanceof \Countable) {
            return \count($items);
        }

        $count = 0;
        foreach ($items as $_) {
            $count++;
        }
        return $count;
    }
}
