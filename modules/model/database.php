<?php

/**
 * Classe abstraite pour la gestion de la connexion à la base de données PostgreSQL.
 */
abstract class Database
{
    // Instance statique de la connexion à la base de données
    private static $_bdd;

    /**
     * Charge les variables d'environnement depuis le fichier .env.
     */
    private static function loadEnv()
    {
        $envFile = __DIR__ . '/../../.env';

        if (!file_exists($envFile)) {
            die("Erreur : fichier .env introuvable");
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $_ENV[trim($key)] = trim($value);
            }
        }
    }

    /**
     * Instancie la connexion à la base de données PostgreSQL.
     */
    private static function setBdd()
    {
        self::loadEnv();

        $host = $_ENV['DB_HOST'];
        $port = $_ENV['DB_PORT'] ?? 5432; // valeur par défaut PostgreSQL
        $dbname = $_ENV['DB_NAME'];
        $user = $_ENV['DB_USER'];
        $pass = $_ENV['DB_PASS'];

        try {
            // Chaîne DSN pour PostgreSQL
            $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$pass";

            self::$_bdd = new PDO($dsn);
            self::$_bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Erreur de connexion PostgreSQL : " . $e->getMessage());
        }
    }

    /**
     * Récupère la connexion PDO.
     */
    public function getBdd()
    {
        if (self::$_bdd === null) {
            self::setBdd();
        }
        return self::$_bdd;
    }
}