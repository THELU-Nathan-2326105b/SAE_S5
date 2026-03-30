<?php

namespace App\Tests\Service;

use App\Service\IsPresentImportService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour le service d'import IsPresentImportService.
 */
class IsPresentImportServiceTest extends TestCase
{
    /**
     * Vérifie qu'une exception est levée si les champs obligatoires (forum_id) sont manquants.
     *
     * @return void
     */
    public function testImportEchoueSiForumIdEstManquant(): void
    {
        $connectionMock = $this->createMock(Connection::class);
        $service = new IsPresentImportService($connectionMock);

        $lignesCsv = [['company_name' => 'Tech Solutions']
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("forum_id ou company_name manquant dans le CSV");

        $service->importFromCsv($lignesCsv);
    }

    /**
     * Vérifie que l'import réussit et assigne correctement les valeurs par défaut.
     *
     * @return void
     */
    public function testImportReussiAvecValeursParDefaut(): void
    {
        $connectionMock = $this->createMock(Connection::class);
        $connectionMock->expects($this->exactly(2))->method('executeStatement');
        $service = new IsPresentImportService($connectionMock);

        $lignesCsv = [[
            'forum_id' => 1,
            'company_name' => 'Tech Solutions',
            'start_time' => '09:00',
            'end_time' => '17:00'
        ]];

        $nbInseres = $service->importFromCsv($lignesCsv);

        $this->assertEquals(1, $nbInseres);
    }

    /**
     * Vérifie qu'une exception est levée s'il manque les heures de présence.
     *
     * @return void
     */
    public function testImportEchoueSiHeuresManquantes(): void
    {
        $connectionMock = $this->createMock(Connection::class);
        $service = new IsPresentImportService($connectionMock);

        $lignesCsv = [[
            'forum_id' => 1,
            'company_name' => 'Green Energy',
        ]];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("start_time ou end_time manquant pour Green Energy");

        $service->importFromCsv($lignesCsv);
    }

    /**
     * Vérifie qu'une exception est levée si le type de recherche (search_type) n'est pas autorisé.
     *
     * @return void
     */
    public function testImportEchoueSiSearchTypeEstInvalide(): void
    {
        $connectionMock = $this->createMock(Connection::class);
        $service = new IsPresentImportService($connectionMock);

        $lignesCsv = [[
            'forum_id' => 1,
            'company_name' => 'Alpha Logistics',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'search_type' => 'CDI'
        ]];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("search_type invalide pour Alpha Logistics: CDI");

        $service->importFromCsv($lignesCsv);
    }
}
