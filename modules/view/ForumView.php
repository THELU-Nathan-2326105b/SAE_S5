<!DOCTYPE html>
<html lang="fr">
<head>
    <meta name="viewport" content="width=device-width, maximum-scale=1" />
    <link rel="icon" href="../../_assets/images/icon.webp"/>
    <meta charset="UTF-8" />
    <title>Accueil</title>
    <link rel="stylesheet" href="/_assets/styles/stylesheet.css">
</head>

<body>
<main class="homepage">
    <section class="image-section">
        <img class="background-image" src="/assets/images/homeImage.png">
        <div class="content">
            <h1 id="jouer">Inscrivez vous au FAMES</h1>
            <div class="discover">
                <a href="#" class="button1" onclick="handleInscriptionClick(<?= json_encode($isUserConnected) ?>)">Découvrir le forum</a>
            </div>
        </div>
    </section>
</main>
</body>
</html>
