----------

# Documentation des Tables et Fonctions

Cette documentation décrit la structure des tables et des fonctions associées à la gestion de l'outil.

----------
## **Diagramme entité association**

![Diag_entité_assoc]("./Diag_entite_assoc.png")

----------

## **Table `users`**

La table `users` stocke les informations relatives aux utilisateurs, telles que leur email, mot de passe, rôle, nom, prénom, niveau et la date de leur dernière connexion.

### **Champs** :

-   `user_id`: Identifiant unique de l'utilisateur (clé primaire).

-   `user_email`: L'email de l'utilisateur (doit être unique et respecter un format valide : longueur maximale de 320 caractères, avec un "@" dans les limites définie).

-   `user_pwd`: Mot de passe crypté de l'utilisateur.

-   `user_role`: Rôle de l'utilisateur (peut-être `alternance`, `internship` ou `admin`).

-   `user_firstconnexion`: Indicateur booléen pour savoir si c'est la première connexion de l'utilisateur (valeur par défaut : `TRUE`).

-   `user_firstname`: Prénom de l'utilisateur.

-   `user_lastname`: Nom de famille de l'utilisateur.

-   `user_level`: Niveau d'étude de l'utilisateur (`'B1'`, `'B2'`, `'B3'`, `'M1'`, `'M2'`).

-   `user_lastconnexion`: Date de la dernière connexion de l'utilisateur (valeur par défaut : date et heure actuelles).

-   `user_url_cv`: URL du CV de l'utilisateur (doit être unique).


----------

## **Table `reset_password_request`**

La table `reset_password_request` enregistre les demandes de réinitialisation de mot de passe effectuées par les utilisateurs.

### **Champs** :

-   `id`: Identifiant unique de la demande (clé primaire).

-   `user_id`: Identifiant de l'utilisateur concerné par la demande (clé étrangère vers `users`).

-   `selector`: Sélecteur unique généré pour la demande de réinitialisation de mot de passe.

-   `hashed_token`: Token de réinitialisation de mot de passe crypté.

-   `requested_at`: Date et heure de la demande de réinitialisation.

-   `expires_at`: Date et heure d'expiration de la demande.


----------

## **Table `forum`**

La table `forum` contient des informations sur les forums organisés, tels que le nom, la date et l'adresse du forum.

### **Champs** :

-   `forum_id`: Identifiant unique du forum (clé primaire).

-   `forum_date`: Date du forum (`Not null`).

-   `forum_address`: Adresse du forum.

-   `forum_name`: Nom du forum (`Not null`).


----------

## **Table `company`**

La table `company` contient les informations des entreprises participant aux forums.

### **Champs** :

-   `company_name`: Nom de l'entreprise (clé primaire).

-   `company_description`: Description de l'entreprise.

-   `company_logo`: Logo de l'entreprise (sous forme d'URL).


----------

## **Table `is_present`**

La table `is_present` enregistre la présence des entreprises lors des forums. Elle relie les forums et les entreprises et précise les heures de présence ainsi que les niveaux et le type de contrat recherché.

### **Champs** :

-   `forum_id`: Identifiant du forum (clé étrangère vers `forum`). Si le forum est supprimé, la ligne correspondante est également supprimée.

-   `company_name`: Nom de l'entreprise (clé étrangère vers `company`). Si l'entreprise est supprimée, la ligne correspondante est également supprimée.

-   `start_time`: Heure de début de la présence de l'entreprise au forum.

-   `end_time`: Heure de fin de la présence de l'entreprise.

-   `search_type`: Type de recherche effectué par l'entreprise (`internship`, `alternance` ou `internship;alternance`).

-   `search_level`: Niveau de recherche de l'entreprise (Année recherchée par l'entreprise).


----------

## **Table `appointment`**

La table `appointment` enregistre les rendez-vous pris par les utilisateurs avec les entreprises pendant les forums.

### **Champs** :

-   `user_id`: Identifiant de l'utilisateur (clé étrangère vers `users`).

-   `forum_id`: Identifiant du forum (clé étrangère vers `forum`).

-   `company_name`: Nom de l'entreprise (clé étrangère vers `company`).

-   `appointment_request`: Indicateur booléen indiquant si l'utilisateur a fait une demande de rendez-vous.

-   `appointment_time`: Heure du rendez-vous.


----------

## **Fonction `is_valid_search_level`**

La fonction `is_valid_search_level` vérifie la validité des niveaux de recherche (`search_level`) fournis pour la table `is_present`. Elle s'assure que les niveaux sont valides (par exemple "B1", "M1", etc.) et qu'il n'y a pas de doublons.

### **Entrées** :

-   `levels`: Une chaîne de caractères contenant les niveaux de recherche séparés par des points-virgules.


### **Sortie** :

-   Retourne `TRUE` si les niveaux sont valides et uniques, sinon retourne `FALSE`.


----------

## **Fonction `delete_inactive_users`**

La fonction `delete_inactive_users` supprime les utilisateurs qui ne se sont pas connectés depuis plus de 12 mois.

### **Entrées** :

-   Aucun paramètre d'entrée.


### **Sortie** :

-   Supprime les utilisateurs inactifs dans la table `users` dont la date de dernière connexion est supérieure à 12 mois.


----------

### Conclusion

Ce système permet de gérer les informations des utilisateurs, des forums, des entreprises et des rendez-vous dans un environnement professionnel. Les fonctions d'assistance, comme la validation des niveaux de recherche et la suppression des utilisateurs inactifs, ajoutent une couche de gestion et de maintenance automatique pour garantir l'intégrité des données.
