<!DOCTYPE html>
<html lang="fr">
<head>
    <meta name="viewport" content="width=device-width, maximum-scale=1" />
    <link rel="icon" href="/assets/images/favicon-32x32.ico"/>
    <meta charset="UTF-8" />
    <title>AMOS FAMES - Connexion</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<?php include "NavbarView.php"; ?>
<main class="loginpage">
    <div class="title">
        <span class="title-text_start">Connectez-vous</span>
        <span class="title-text_end">à votre compte</span>
    </div>
    <section class="login-section">
        <form action="index.php?controller=Login&action=loginHandler" method="POST" class="login-form">
            <div class="form-group">
                <label for="email">Adresse email</label>
                <input type="email" id="email" name="email" required placeholder="Votre email">
            </div>
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required placeholder="Votre mot de passe">
            </div>
            <div class="show-password">
                <input type="checkbox" id="showPassword">
                <label for="showPassword">Afficher le mot de passe</label>
            </div>
            <button type="submit" class="validate-btn">Se connecter</button>
        </form>
        <a href="index.php?controller=Login&action=forgotPassword" class="forgot-link">Mot de passe oublié ?</a>
    </section>
</main>
<script src="/assets/javascript/showPassword.js"></script>
</body>
</html>
