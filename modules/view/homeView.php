<!DOCTYPE html>
<html lang="fr">
<head>
    <meta name="viewport" content="width=device-width, maximum-scale=1" />
    <link rel="icon" href="/assets/images/favicon-32x32.ico"/>
    <meta charset="UTF-8" />
    <title>AMOS FAMES - Accueil</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    </head>

<body>
    <nav class="navbar">
        <div class="navbar-logo">
            <img src="/assets/images/LOGO_AMOS_WHITE.png" alt="Logo AMOS">
        </div>
        <ul class="navbar-links">
            <li><a href="/index.php?controller=Home&action=display">ACCUEIL</a></li>
            <li><a href="/index.php?controller=Forum&action=display">FORUM</a></li>
            <li><a href="/index.php?controller=Planning&action=display">PLANNING</a></li>
            <li><a href="/index.php?controller=Admin&action=display">INTERFACE ADMIN</a></li>
        </ul>
        <div class="navbar-action">
            <a href="/index.php?controller=Login&action=display" class="navbar-button">Connexion/Déconnexion</a>
        </div>
    </nav>

    <main class="homepage">
        <section class="image-section">
            <img class="background-image" src="/assets/images/homeImage.png">
            <div class="head-content">
                <div class="headline-text">
                    <span class="headline-text_start">Inscrivez vous</span>
                    <span class="headline-text_end">au FAMES</span>
                </div>
                <div class="head-action">
                    <a href="#" class="button1" onclick=handleForumClick()>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
