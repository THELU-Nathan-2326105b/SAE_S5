<?php

namespace App\Mapper\Users;

use App\Entity\Users;
use InvalidArgumentException;

use App\Mapper\Contract\Mapper as MapperContract;
use DateTimeImmutable;

/**
 * Mapper
 * 
 * Mapper pour l'entité Users.
 * Permet la conversion bidirectionnelle entre tableaux associatifs et entités Users.
 * Utilisé pour l'import/export CSV des utilisateurs avec gestion automatique
 * de la date de dernière connexion.
 * 
 * @package App\Mapper\Users
 */
final class Mapper implements MapperContract{
    /**
     * Vérifie que l'objet donné est bien une instance de App\Entity\Users.
     *
     * Utilité : protéger les conversions (ex. dans toRow()) pour s'assurer
     * que ce mapper ne traite que des entités Users.
     *
     * @param object $entity Objet à vérifier.
     * @return bool true si $entity est un Users, false sinon.
    */

    private function isValidEntity(object $entity):bool{
        return $entity instanceof Users; 
    }

    /**
     * Convertit une ligne associative en entité Users.
    */
    public function fromRow(array $row): object
    {
        $user = new Users();
        
        // Validation des champs obligatoires
        $email = isset($row['user_email']) ? trim((string) $row['user_email']) : '';
        $firstname = isset($row['user_firstname']) ? trim((string) $row['user_firstname']) : '';
        $lastname = isset($row['user_lastname']) ? trim((string) $row['user_lastname']) : '';
        $role = isset($row['user_role']) ? trim((string) $row['user_role']) : '';
        $level = isset($row['user_level']) ? trim((string) $row['user_level']) : '';
        
        // Vérifier que les champs requis ne sont pas vides
        if ($email === '') {
            throw new \RuntimeException(" : L'email d'un des utilisateurs est vide.");
        }
        if ($firstname === '') {
            throw new \RuntimeException(" : Le prénom d'un des utilisateurs est vide.");
        }
        if ($lastname === '') {
            throw new \RuntimeException(" : Le nom d'un des utilisateurs est vide.");
        }
        if ($role === '') {
            throw new \RuntimeException(" : Le rôle d'un des utilisateurs est vide.");
        }
        if ($level === '') {
            throw new \RuntimeException(" : Le niveau d'un des utilisateurs est vide.");
        }
        
        $user->setUserEmail($email);
        $user->setUserFirstname($firstname);
        $user->setUserLastname($lastname);
        $user->setUserRole($role);
        $user->setUserLevel($level);
        $user->setUserLastconnexion(new DateTimeImmutable('now'));
        
        return $user;
    }

    
    /**
     * Convertit une entité Users en tableau associatif (pour export).
     * @throws InvalidArgumentException si l'objet n'est pas une Users.
     */
    public function toRow(object $entity): array{
        if(!$this->isValidEntity($entity)){
            throw new InvalidArgumentException('UsersMapper attend App\Entity\Users.');
        }
        else{
            $row=[];
            $row['user_email']=$entity->getUserEmail();
            $row['user_firstname']=$entity->getUserFirstname();
            $row['user_lastname']=$entity->getUserLastname();
            $row['user_pwd']=$entity->getUserPwd();
            $row['user_role']=$entity->getUserRole();
            $row['user_lastconnexion'] = $entity->getUserLastconnexion()?->format('Y-m-d H:i:s');
            $row['user_level']=$entity->getUserLevel();
            return $row;
        }
        
    }


}

