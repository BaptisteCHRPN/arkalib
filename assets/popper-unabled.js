document.addEventListener('DOMContentLoaded', function() {
    if (window.innerWidth <= 767) {
        const dropdowns = document.querySelectorAll('.budget-view-mobile [data-bs-toggle="dropdown"]');
        
        dropdowns.forEach(function(dropdown) {
            // Désactiver Popper.js complètement
            dropdown.addEventListener('show.bs.dropdown', function(event) {
                // Empêcher le comportement par défaut de Popper
                const menu = this.nextElementSibling;
                if (menu && menu.classList.contains('dropdown-menu')) {
                    // Forcer le positionnement statique
                    menu.style.position = 'absolute';
                    menu.style.transform = 'none';
                    menu.style.top = '100%';
                    menu.style.right = '0';
                    menu.style.left = 'auto';
                    menu.style.margin = '2px 0 0 0';
                }
            });
            
            // Verrouiller la position de scroll
            dropdown.addEventListener('click', function(e) {
                const scrollY = window.scrollY;
                
                requestAnimationFrame(function() {
                    window.scrollTo(0, scrollY);
                });
                
                // Double vérification après un petit délai
                setTimeout(function() {
                    window.scrollTo(0, scrollY);
                }, 10);
            });
        });
        
        // Empêcher le scroll lors de l'ouverture
        document.addEventListener('shown.bs.dropdown', function(event) {
            if (event.target.closest('.budget-view-mobile')) {
                const scrollY = window.scrollY;
                window.scrollTo(0, scrollY);
            }
        });
    }
});