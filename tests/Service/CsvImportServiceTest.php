<?php

namespace App\Tests\Service;

use App\Service\CsvImportService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Tests unitaires pour le service de manipulation des fichiers CSV.
 */
class CsvImportServiceTest extends TestCase
{
    /**
     * Vérifie que la méthode countItems compte correctement les éléments d'un tableau itérable.
     *
     * @return void
     */
    public function testCountItemsCompteUnTableauCorrectement(): void
    {
        $kernelMock = $this->createMock(KernelInterface::class);
        $kernelMock->method('getProjectDir')->willReturn('/tmp/projet');

        $service = new CsvImportService($kernelMock);

        $elements =['Ligne 1', 'Ligne 2', 'Ligne 3'];
        $resultat = $service->countItems($elements);

        $this->assertEquals(3, $resultat);
    }

    /**
     * Vérifie que les entités sont bien préparées puis sauvegardées en une fois.
     *
     * @return void
     */
    public function testPersistImportedEntitiesAppellePersistEtFlush(): void
    {
        $kernelMock = $this->createMock(KernelInterface::class);
        $kernelMock->method('getProjectDir')->willReturn('/tmp/projet');

        $service = new CsvImportService($kernelMock);

        $entityManagerMock = $this->createMock(EntityManagerInterface::class);

        $entitesFactices =[new \stdClass(), new \stdClass()];

        $entityManagerMock->expects($this->exactly(2))->method('persist');
        $entityManagerMock->expects($this->once())->method('flush');

        $service->persistImportedEntities($entitesFactices, $entityManagerMock);
    }
}
