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
        <div class="bubble">
            <div class="left">
                <input type="checkbox" id="entreprise1" name="entreprises[]" value="Entreprise A">
                <label for="entreprise1">Entreprise A</label>
            </div>
            <span class="info" title="Entreprise A : spécialisée dans le développement web">i</span>
        </div>

        <div class="bubble">
            <div class="left">
                <input type="checkbox" id="entreprise2" name="entreprises[]" value="Entreprise B">
                <label for="entreprise2">Entreprise B</label>
            </div>
            <span class="info" title="Entreprise B : spécialisée dans le marketing digital">i</span>
        </div>

        <div class="bubble">
            <div class="left">
                <input type="checkbox" id="entreprise3" name="entreprises[]" value="Entreprise C">
                <label for="entreprise3">Entreprise C</label>
            </div>
            <span class="info" title="Entreprise C : spécialisée dans la data science">i</span>
        </div>

        <div class="bubble">
            <div class="left">
                <input type="checkbox" id="entreprise4" name="entreprises[]" value="Entreprise D">
                <label for="entreprise4">Entreprise D</label>
            </div>
            <span class="info" title="Entreprise D : spécialisée dans le cloud computing">i</span>
        </div>

        <div class="bubble">
            <div class="left">
                <input type="checkbox" id="entreprise5" name="entreprises[]" value="Entreprise E">
                <label for="entreprise5">Entreprise E</label>
            </div>
            <span class="info" title="Entreprise E : spécialisée dans le commerce">i</span>
        </div>

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
