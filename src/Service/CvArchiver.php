<?php

namespace App\Service;

use App\Entity\Company;
use App\Entity\Forum;
use App\Entity\Users;
use App\Repository\AppointmentRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use ZipArchive;

class CvArchiver
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
        private AppointmentRepository $appointmentRepository
    ) {
    }

    /**
     */
    public function generateCompanyZip(Company $company, Forum $forum): ?string
    {
        //Récupération des données
        $appointments = $this->getConfirmedAppointments($company, $forum);
        
        if (empty($appointments)) {
            return null;
        }

        //Préparation du fichier ZIP
        $zipPath = $this->generateZipFilePath($company);
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception("Impossible de créer le fichier ZIP temporaire.");
        }
        else{
            //Remplissage du ZIP
            $countAdded = $this->fillZipWithCvs($zip, $appointments);
            $zip->close();
            if ($countAdded === 0) {
                $this->removeFile($zipPath);
                return null;
            }

            return $zipPath;
            }

      
    }


    private function getConfirmedAppointments(Company $company, Forum $forum): array
    {
        return $this->appointmentRepository->findBy([
            'forum' => $forum,
            'companyName' => $company->getCompanyName(),
            'appointmentRequest' => true 
        ]);
    }

    private function generateZipFilePath(Company $company): string
    {
        $safeCompanyName = $this->sanitizeString($company->getCompanyName()); 
        
        $filename = sprintf('Export_%s_%s.zip',$safeCompanyName,date('Ymd_His'));

        return sys_get_temp_dir() . '/' . $filename;
    }

    private function fillZipWithCvs(ZipArchive $zip, array $appointments): int
    {
        $count = 0;

        foreach ($appointments as $appointment) {
            /** @var Users $user */
            $user = $appointment->getUser();
            
            // Récupère le chemin physique du fichier
            $sourcePath = $this->getPhysicalPath($user);

            if ($sourcePath && file_exists($sourcePath)) {
                // Définit le nom propre dans le ZIP
                $destName = $this->generateArchiveFilename($user, $sourcePath);
                
                $zip->addFile($sourcePath, $destName);
                $count++;
            }
        }

        return $count;
    }

    private function getPhysicalPath(Users $user): ?string
    {
        $relativeUrl = $user->getUserUrlCv();
        
        if (!$relativeUrl) {
            return null;
        }
        return $this->projectDir . '/public' . $relativeUrl;
    }


    /**
     * Définit le nom du fichier tel qu'il apparaîtra DANS le fichier ZIP.
     * * @param Users $user L'étudiant concerné
     * @param string $sourcePath Le chemin du fichier original (pour récupérer l'extension .pdf/.doc)
     * @return string Le nouveau nom (ex: "124.pdf")
     */
    private function generateArchiveFilename(Users $user, string $sourcePath): string
    {
        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION);

        // Nommage par ID unique de l'user
        return $user->getId() . '.' . $extension;
    }

    private function sanitizeString(string $string): string
    {
        $string = transliterator_transliterate('Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove', $string);
        //echo $string;
        return $string;
    }

    private function removeFile(string $path): void
    {
        if (file_exists($path)) {
            unlink($path);
        }
    }
}