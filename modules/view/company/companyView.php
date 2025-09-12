<?php
/** @var array $companies */
/** @var array $selected */
?>

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
        <?php foreach ($companies as $company): ?>
            <div class="bubble">
                <div class="left">
                    <input type="checkbox"
                           id="entreprise<?= htmlspecialchars($company['company_name']) ?>"
                           name="entreprises[]"
                           value="<?= htmlspecialchars($company['company_name']) ?>">
                    <label for="entreprise<?= htmlspecialchars($company['company_name']) ?>">
                        <?= htmlspecialchars($company['company_name']) ?>
                    </label>
                </div>
                <span class="info" title="<?= htmlspecialchars($company['company_description']) ?>">i</span>
            </div>
        <?php endforeach; ?>
        <button type="submit">Valider les choix</button>
    </form>

<?php else: ?>
    <!-- État 2 : page dédiée aux entreprises sélectionnées -->
    <div class="step2-container">
        <h2><span class="first-word">Entreprises</span> sélectionnées</h2>

        <div class="selected-companies">
            <?php foreach ($selected as $entreprise): ?>
                <div class="company-bubble">
                    <?= htmlspecialchars($entreprise) ?>
                </div>
            <?php endforeach; ?>
        </div>

        <form action="" method="post" enctype="multipart/form-data" class="upload-form">
            <!-- Upload du CV -->
            <div class="file-upload-container">
                <label for="cv" class="file-label">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="17 8 12 3 7 8"></polyline>
                        <line x1="12" y1="3" x2="12" y2="15"></line>
                    </svg>
                    <span>Déposez votre CV</span>
                </label>
                <input type="file" id="cv" name="cv" accept=".pdf,.doc,.docx" class="file-input">
                <div class="file-info" id="fileInfo">Aucun fichier sélectionné</div>
            </div>

            <div class="buttons-container">
                <a href="" class="back-button">Revenir au formulaire</a>
                <button type="submit" id="submitBtn" class="submit-button" disabled>Valider l'envoi</button>
            </div>
        </form>
    </div>

    <script>
        const fileInput = document.getElementById('cv');
        const submitBtn = document.getElementById('submitBtn');
        const fileInfo = document.getElementById('fileInfo');

        fileInput.addEventListener('change', function() {
            const files = fileInput.files;
            submitBtn.disabled = files.length === 0;

            if (files.length > 0) {
                fileInfo.textContent = `${files[0].name} (${formatFileSize(files[0].size)})`;
                fileInfo.style.color = '#008b57';
            } else {
                fileInfo.textContent = 'Aucun fichier sélectionné';
                fileInfo.style.color = '#666';
            }
        });

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    </script>
<?php endif; ?>

</body>
</html>
