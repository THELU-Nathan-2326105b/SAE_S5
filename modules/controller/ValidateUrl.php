<?php

function ValidateUrl($url) {
    // Analyser l'URL
    $parsedUrl = parse_url($url);

    // Si l'URL est mal formatée, retourner une erreur
    if ($parsedUrl === false) {
        die("URL invalide.");
    }

    // Nom de domaine autorisé
    $allowedDomain = 'bs.alwaysdata.net';

    // Vérifier si l'hôte de l'URL correspond à l'hôte autorisé
    if ($parsedUrl['host'] !== $allowedDomain) {
        die("Accès interdit. Seul le domaine '$allowedDomain' est autorisé.");
    }

    return true;  // Si l'URL est valide et autorisée, retourner true
}
?>
