<?php
use blog\controller\HomeController;
?>


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
    <?php include "NavbarView.php";?>

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
