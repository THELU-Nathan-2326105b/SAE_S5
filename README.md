<h1>Guide de déploiement de AMOS FAMES</h1>

<h2>Téléchargement</h2>
<p>Téléchargez le dossier .zip</p>

<h2>Complétion du .env</h2>
<ol>Compléter le fichier .env en suivant les instructions suivantes:
    <li><code>DATABASE_URL=</code> mettez ici l'url qui permet d'accéder à votre la base de données dans le format suivant <code>postgresql://<em>nomUtilisateurBD</em>:<em>mdpUtilisateurBD</em>@<em>urlBD</em>:5432/<em>nomBD</em>?serverVersion=16&charset=utf8"</code></li>
    <li><code>MAILER_DSN=</code> mettez ici l'url qui permet d'accéder à votre service de mail dans le format suivant<code>MAILER_DSN=smtp://nomUtilisateurSMTP:mdpSMTP@serveurSMTP:587?encryption=tls</code></li>
    <li><code>GOOGLE_RECAPTCHA_SITE_KEY=</code> pour activer le captcha, il va falloir accéder à https://www.google.com/recaptcha/admin/create?hl=fr afin de créer sa clé de captcha<br>
    <img width="589" height="652" alt="image" src="https://github.com/user-attachments/assets/4da28ebc-27d1-452c-a115-f5fdd05bc8e9" /> Donnez lui un nom et rajoutez votre site à la liste des sites autorisées comme dans l'exemple puis copiez la clé secrète.</li>
</ol>

<h2>Mise en place du code</h2>
<ol>
  <li>Déposez le code télechargé sur le serveur.</li>
  <li>Lancer la commande <code>composer update</code> dans le répertoire où le code est situé.</li>
  <li>Lancer la commande <code>composer install --no-dev --optimize-autoloader</code> toujours dans le même répertoire.</li>
</ol>
