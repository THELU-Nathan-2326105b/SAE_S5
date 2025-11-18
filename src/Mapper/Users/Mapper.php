<?php

namespace App\Mapper\Users;

use App\Entity\Users;
use InvalidArgumentException;

use App\Mapper\Contract\Mapper as MapperContract;
use DateTimeImmutable;

/**
 * UsersMapper
 *
 * Rôle :
 * - fromRow() : transforme un tableau associatif (ex. lu depuis CSV) en entité Users.
 * - toRow()   : transforme une entité Users en tableau associatif prêt à écrire (CSV).
 *
**/
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
    public function fromRow(array $row):object{
        $user=new Users();
        // dd($row);
        $user->setUserFirstname($row['user_firstname']);
        $user->setUserLastname($row['user_lastname']);
        $user->setUserEmail($row['user_email']);
        $user->setUserRole($row['user_role']);
        $user->setUserLastconnexion(new DateTimeImmutable('now'));
        $user->setUserLevel($row['user_level']);
        
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

