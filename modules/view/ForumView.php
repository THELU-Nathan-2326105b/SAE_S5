<!DOCTYPE html>
<html lang="fr">
<head>
    <meta name="viewport" content="width=device-width, maximum-scale=1" />
    <link rel="icon" href="/assets/images/favicon-32x32.ico"/>
    <meta charset="UTF-8" />
    <title>AMOS FAMES - Forum</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>

<body>
<?php include "NavbarView.php";?>

<main class="forumpage">
    <div class="title">
        <span class="title-text_start">Choisissez</span>
        <span class="title-text_end">vos entreprises</span>
    </div>

    <!--à mettre dans une boucle php pour chaque entreprise-->
    <section class="company-section">
        <div class="company-list">
            <label class="company">
                <input type="checkbox">
                <span class="checkmark"></span>
                <span class="company-name">Entreprise</span>
                <span class="info">i</span>
            </label>
        </div>
        <button class="validate-btn">Valider les choix</button>
        <p class="note">(Cliquable si 1 choix ou plus effectué)</p>
    </section>
</main>
</body>
</html>
