<?php

require '../includes/Autoloader.php';


if (session_status() === PHP_SESSION_NONE) {
    // Démarre la session uniquement si elle n'est pas déjà démarrée
    session_start([
        'use_strict_mode' => true,
        'cookie_httponly' => true,
        'cookie_secure' => true,
        'cookie_samesite' => 'None'
    ]);
}

if (isset($_SESSION['last_activity'])) {
    $timeout = 600; // 10 minutes
    $inactive_time = time() - $_SESSION['last_activity'];

    if ($inactive_time > $timeout) {
        session_unset();
        session_destroy();
        echo "<script>window.location.href = '/index.php?controller=connexion&action=logout';</script>"; // Redirection instantanée
        exit();
    }
}

$_SESSION['last_activity'] = time(); // Met à jour le temps d'activité
header("Content-Security-Policy: style-src 'self' 'unsafe-inline'; img-src 'self' https://www.google.com; font-src 'self'; frame-src 'self' https://www.google.com https://www.recaptcha.net;");

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Gestion des erreurs fatales
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_USER_ERROR)) {
        // Log de l'erreur fatale (optionnel)
        error_log("Erreur fatale : " . $error['message']);

        // Redirection vers la page 404
        header("Location: /404.php");
        exit();
    }
});

$S_controller = $_GET['controller'] ?? 'Home';
$S_action = $_GET['action'] ?? 'display';
// Ouvre le tampon d'affichage pour stocker la sortie
// /public/index.php (ou routeur principal)




ViewHandler::bufferStart();

try {
    // Exécution du contrôleur et de l'action
    $C_controller = new ControllerHandler($S_controller, $S_action);
    $C_controller->execute();
} catch (Exception $e) {
    // En cas d'exception, redirection vers la page 404
    header("Location: /404.php");
    //var_dump($e->getMessage()); 
    exit();
}

// Récupère le contenu tamponné
$displayContent = ViewHandler::bufferCollect();
$A_params = $C_controller->getParams();


echo $displayContent;