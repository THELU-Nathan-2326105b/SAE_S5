<!DOCTYPE html>
<html lang="fr">
<head>
    <meta name="viewport" content="width=device-width, maximum-scale=1" />
    <link rel="icon" href="../../_assets/images/icon.webp"/>
    <meta name="description" content="Site de création de planning de réunion pour des élèves participant à un forum">
    <meta name="keywords" content="HTML, CSS, PHP, planning, forum, étudiant">
    <meta name="author" content="Avias Daphné, Curt Elien, Daubrege Simon, Pellet Casimir, Thélu Nathan">
    <meta charset="UTF-8" />
    <title>Profil</title>
    <link rel="stylesheet" href="/_assets/styles/stylesheet.css">
    </head>

<body>
    <?php include "NavbarView.php";?>

    <h1>Profil étudiant</h1>
    <p> Nom : <?php echo htmlspecialchars($nom); ?></p>
    <p> Prenom : <?php echo htmlspecialchars($prenom); ?></p>
    <p> Mail : <?php echo htmlspecialchars($_SESSION['mail']) ?></p>
    <p> Année : <?php echo $annee ?></p>
    <p>Il y a un problème dans les information affichées ? Contactez un administrateur ici : <em>Numéro de téléphone ou mail</em></p>
    
<footer>
    <p>© 2025 FAMES, Amos Business school. Tous droits réservés.</p>
</footer>
</body>
</html>
