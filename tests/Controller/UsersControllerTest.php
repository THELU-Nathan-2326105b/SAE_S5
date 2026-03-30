<?php

namespace App\Tests\Controller;

use App\Controller\UsersController;
use App\Entity\Users;
use App\Import\Contract\ImporterFactory;
use App\Service\CsvImportService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Tests unitaires pour le UsersController.
 *
 * Utilise exclusivement des Mocks. Aucune connexion à la base de données n'est requise.
 */
class UsersControllerTest extends TestCase
{
    private UsersController $controller;
    private Container $containerMock;

    /**
     * Prépare le Controller et simule le conteneur interne de Symfony avant chaque test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->controller = new UsersController();

        $this->containerMock = new Container();
        $this->controller->setContainer($this->containerMock);
    }

    /**
     * Vérifie que la tentative de suppression d'un utilisateur est annulée si le jeton CSRF est invalide.
     *
     * @return void
     */
    public function testDeleteWithoutCsrfTokenIsRejected(): void
    {
        $entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $csrfManagerMock = $this->createMock(CsrfTokenManagerInterface::class);

        $csrfManagerMock->method('isTokenValid')->willReturn(false);
        $this->containerMock->set('security.csrf.token_manager', $csrfManagerMock);

        $request = new Request();
        $request->request->set('_token', 'mauvais_token_invente');

        $user = new Users();

        $entityManagerMock->expects($this->never())->method('remove');
        $entityManagerMock->expects($this->never())->method('flush');

        try {
            $this->controller->delete($request, $user, $entityManagerMock);
        } catch (\Exception $e) {
        }
    }

    /**
     * Vérifie que le contrôleur interdit le traitement d'un fichier malveillant lors de l'import.
     *
     * @return void
     */
    public function testImportRejectsMaliciousFileType(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'malicious') . '.php';
        file_put_contents($tmpFile, '<?php echo "piratage"; ?>');

        $uploadedFile = new UploadedFile(
            $tmpFile,
            'shell.php',
            'application/x-php',
            null,
            true
        );

        $request = new Request();
        $request->files->set('csvFile', $uploadedFile);

        $entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $importerFactoryMock = $this->createMock(ImporterFactory::class);
        $csvImportServiceMock = $this->createMock(CsvImportService::class);

        $entityManagerMock->expects($this->never())->method('persist');
        $entityManagerMock->expects($this->never())->method('flush');

        try {
            $this->controller->import(
                $request,
                $entityManagerMock,
                $importerFactoryMock,
                $csvImportServiceMock
            );
        } catch (\Exception $e) {
        }

        unlink($tmpFile);
    }
}
