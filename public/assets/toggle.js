/* assets/js/toggle.js */

document.addEventListener('DOMContentLoaded', () => {
    
    // 1. On cherche tous les éléments qui ont la classe "js-toggle-btn"
    const triggers = document.querySelectorAll('.js-toggle-btn');

    triggers.forEach(button => {
        button.addEventListener('click', (e) => {
            e.preventDefault(); // Empêche le saut de page si c'est un lien

            // 2. On récupère l'ID de la cible via l'attribut "data-target"
            const targetId = button.getAttribute('data-target');
            const targetElement = document.getElementById(targetId);

            if (targetElement) {
                // 3. On bascule l'affichage (toggle)
                if (targetElement.style.display === 'block') {
                    targetElement.style.display = 'none';
                    button.classList.remove('active'); // Pour la flèche CSS
                } else {
                    targetElement.style.display = 'block';
                    button.classList.add('active');
                }
            }
        });
    });
});