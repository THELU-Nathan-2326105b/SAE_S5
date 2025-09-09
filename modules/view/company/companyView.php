<!DOCTYPE html>
<html lang="fr">
<head>
    <meta name="viewport" content="width=device-width, maximum-scale=1" />
    <link rel="icon" href="../../_assets/images/icon.webp"/>
    <meta charset="UTF-8" />
    <title>Choix des entreprises</title>
    <link rel="stylesheet" href="/_assets/styles/company.css">
</head>

<body>
<h1><span class="first-word">Choisissez</span> vos entreprises</h1>

<?php if (empty($selected)): ?>
    <!-- État 1 : formulaire avec checkboxes -->
    <form action="" method="post" enctype="multipart/form-data">
        <div>
            <input type="checkbox" id="entreprise1" name="entreprises[]" value="Entreprise A">
            <label for="entreprise1">
                Entreprise A
                <span class="info" title="Entreprise A : spécialisée dans le développement web">i</span>
            </label>
        </div>
        <div>
            <input type="checkbox" id="entreprise2" name="entreprises[]" value="Entreprise B">
            <label for="entreprise2">
                Entreprise B
                <span class="info" title="Entreprise B : spécialisée dans le marketing digital">i</span>
            </label>
        </div>
        <div>
            <input type="checkbox" id="entreprise3" name="entreprises[]" value="Entreprise C">
            <label for="entreprise3">
                Entreprise C
                <span class="info" title="Entreprise C : spécialisée dans la data science">i</span>
            </label>
        </div>
        <div>
            <input type="checkbox" id="entreprise4" name="entreprises[]" value="Entreprise D">
            <label for="entreprise4">
                Entreprise D
                <span class="info" title="Entreprise D : spécialisée dans le cloud computing">i</span>
            </label>
        </div>

        <br>
        <button type="submit">Valider les choix</button>
    </form>
<?php else: ?>
    <!-- État 2 : page dédiée aux entreprises sélectionnées -->
    <h2>Entreprises sélectionnées</h2>
    <ul>
        <?php foreach ($selected as $entreprise): ?>
            <li><?= htmlspecialchars($entreprise) ?></li>
        <?php endforeach; ?>
    </ul>

    <form action="" method="post" enctype="multipart/form-data">
        <!-- Upload du CV -->
        <div>
            <label for="cv">Déposez votre CV :</label>
            <input type="file" id="cv" name="cv" accept=".pdf,.doc,.docx">
        </div>

        <br>
        <a href="">Retour au formulaire</a>
        <button type="submit" id="submitBtn" disabled>Valider</button>
    </form>

    <script>
        const fileInput = document.getElementById('cv');
        const submitBtn = document.getElementById('submitBtn');

        fileInput.addEventListener('change', function() {
            if (fileInput.files.length > 0) {
                submitBtn.disabled = false;
            } else {
                submitBtn.disabled = true;
            }
        });
    </script>

<?php endif; ?>

</body>
</html>
