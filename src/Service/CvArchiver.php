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
     * Génère un fichier ZIP contenant les CV des utilisateurs d'une entreprise
     * pour un forum donné. Ajoute un README pour les CV manquants.
     *
     * @param Company $company L'entreprise pour laquelle générer le ZIP
     * @param Forum $forum Forum associé aux rendez-vous
     * @param Users[] $usersWithCv Liste des utilisateurs avec CV existant
     * @param string[] $missingCvUsers Liste des noms d'utilisateurs sans CV
     * @param string[] $fileMissingUsers Liste des noms d'utilisateurs dont le fichier CV est introuvable
     * @return string|null Chemin vers le fichier ZIP généré ou null si aucun CV
     */
    public function generateCompanyZip(
        Company $company,
        Forum $forum,
        array $usersWithCv,
        array $missingCvUsers = [],
        array $fileMissingUsers = []
    ): ?string {
        if (empty($usersWithCv)) {
            return null;
        }

        $zipPath = $this->generateZipFilePath($company);
        $zip = new \ZipArchive();

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \Exception("Impossible de créer le fichier ZIP temporaire.");
        }

        $this->fillZipWithCvs($zip, $usersWithCv);

        if (!empty($missingCvUsers) || !empty($fileMissingUsers)) {
            $readmeContent = "Certaines CV n'ont pas pu être inclus dans ce ZIP :\n\n";

            if (!empty($missingCvUsers)) {
                $readmeContent .= "CV non fourni pour : " . implode(', ', array_unique($missingCvUsers)) . "\n";
            }

            if (!empty($fileMissingUsers)) {
                $readmeContent .= "Fichier CV manquant pour : " . implode(', ', array_unique($fileMissingUsers)) . "\n";
            }

            $zip->addFromString('README.txt', $readmeContent);
        }

        $zip->close();

        return $zipPath;
    }

    /**
     * Génère un chemin complet pour le fichier ZIP temporaire d'une entreprise.
     *
     * @param Company $company L'entreprise pour laquelle créer le fichier ZIP
     * @return string Chemin complet vers le fichier ZIP
     */
    private function generateZipFilePath(Company $company): string
    {
        $safeCompanyName = $this->sanitizeString($company->getCompanyName());

        $filename = sprintf('Export_%s_%s.zip',$safeCompanyName,date('Ymd_His'));

        return sys_get_temp_dir() . '/' . $filename;
    }

    /**
     * Ajoute les CV des utilisateurs au fichier ZIP.
     *
     * @param ZipArchive $zip Instance de l'archive ZIP à remplir
     * @param Appointment[] $appointments Liste des rendez-vous contenant les utilisateurs
     * @return int Nombre de fichiers ajoutés au ZIP
     */
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


    /**
     * Génère le nom du fichier tel qu'il apparaîtra dans le ZIP.
     *
     * @param Users $user Utilisateur dont on veut le nom du fichier
     * @param string $sourcePath Chemin réel du fichier CV
     * @return string Nom sécurisé du fichier pour l'archive ZIP
     */
    private function generateArchiveFilename(Users $user, string $sourcePath): string
    {
        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION);

        // Remplace les espaces et caractères spéciaux pour le nom de fichier
        $safeFirstname = preg_replace('/[^A-Za-z0-9]/', '_', $user->getUserFirstname() ?? 'Prenom');
        $safeLastname  = preg_replace('/[^A-Za-z0-9]/', '_', $user->getUserLastname() ?? 'Nom');

        return $safeFirstname . '_' . $safeLastname . '_CV.' . $extension;
    }


    /**
     * Nettoie et sécurise une chaîne pour l'utiliser dans un nom de fichier.
     * Supprime les caractères spéciaux et accentués.
     *
     * @param string $string Chaîne à nettoyer
     * @return string Chaîne sécurisée
     */
    private function sanitizeString(string $string): string
    {
        $string = transliterator_transliterate('Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove', $string);
        return $string;
    }


    /**
     * Génère un fichier ZIP contenant tous les CV des utilisateurs pour un forum donné.
     * Ajoute un README pour les CV manquants.
     *
     * @param Forum $forum Forum pour lequel générer le ZIP
     * @param Users[] $usersWithCv Liste des utilisateurs avec CV existant
     * @param string[] $missingCvUsers Liste des noms d'utilisateurs sans CV
     * @param string[] $fileMissingUsers Liste des noms d'utilisateurs dont le fichier CV est introuvable
     * @return string|null Chemin vers le fichier ZIP généré ou null si aucun CV
     */
    public function generateForumZip(Forum $forum, array $usersWithCv, array $missingCvUsers = [], array $fileMissingUsers = []): ?string
    {
        if (empty($usersWithCv)) {
            return null;
        }

        $zipPath = sys_get_temp_dir() . '/Export_Forum_' . $forum->getId() . '_' . date('Ymd_His') . '.zip';
        $zip = new \ZipArchive();

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \Exception("Impossible de créer le fichier ZIP forum.");
        }

        // Ajouter les CV valides
        foreach ($usersWithCv as $user) {
            $sourcePath = $this->getPhysicalPath($user);
            if ($sourcePath && file_exists($sourcePath)) {
                $destName = $this->generateArchiveFilename($user, $sourcePath);
                $zip->addFile($sourcePath, $destName);
            }
        }

        // Ajouter README si nécessaire
        if (!empty($missingCvUsers) || !empty($fileMissingUsers)) {
            $readmeContent = "Certaines CV n'ont pas pu être inclus dans ce ZIP :\n\n";

            if (!empty($missingCvUsers)) {
                $readmeContent .= "CV non fourni pour : " . implode(', ', array_unique($missingCvUsers)) . "\n";
            }

            if (!empty($fileMissingUsers)) {
                $readmeContent .= "Fichier CV manquant pour : " . implode(', ', array_unique($fileMissingUsers)) . "\n";
            }

            $zip->addFromString('README.txt', $readmeContent);
        }

        $zip->close();

        return $zipPath;
    }



    /**
     * Récupère tous les rendez-vous confirmés pour une entreprise et un forum donné.
     *
     * @param Company $company L'entreprise ciblée
     * @param Forum $forum Forum associé
     * @return array Liste d'objets Appointment correspondant aux critères
     */
    public function getConfirmedAppointments(Company $company, Forum $forum): array
    {
        return $this->appointmentRepository->findBy([
            'forum' => $forum,
            'companyName' => $company->getCompanyName()
        ]);
    }

    /**
     * Récupère tous les rendez-vous pour un forum donné.
     *
     * @param Forum $forum Forum ciblé
     * @return array Liste d'objets Appointment pour ce forum
     */
    public function getForumAppointments(Forum $forum): array
    {
        return $this->appointmentRepository->findBy([
            'forum' => $forum
        ]);
    }

    /**
     * Retourne le chemin physique complet vers le CV d'un utilisateur.
     *
     * @param Users $user Utilisateur pour lequel récupérer le CV
     * @return string|null Chemin complet du fichier CV ou null si inexistant
     */
    public function getPhysicalPath(Users $user): ?string
    {
        $relativeUrl = $user->getUserUrlCv();
        if (!$relativeUrl) {
            return null;
        }
        return $this->projectDir . '/public' . $relativeUrl;
    }
}
