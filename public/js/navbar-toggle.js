// navbar-toggle.js
document.addEventListener('DOMContentLoaded', function() {
    // Récupération des éléments
    const navbarToggle = document.getElementById('navbarToggle');
    const navbarClose = document.getElementById('navbarClose');
    const sidebar = document.getElementById('sidebarNav');
    const body = document.body;
    
    // Vérifier que Bootstrap est chargé
    if (typeof bootstrap === 'undefined') {
        console.error('Bootstrap n\'est pas chargé !');
        return;
    }
    
    // Instance Bootstrap Offcanvas
    const bsOffcanvas = new bootstrap.Offcanvas(sidebar, {
        backdrop: false,
        scroll: true
    });

    // Fonction pour vérifier si on est sur mobile
    function isMobile() {
        return window.innerWidth < 992;
    }

    // Initialiser l'état au chargement SANS animation
    function initNavbarState() {
        // DÉSACTIVER LES TRANSITIONS TEMPORAIREMENT
        sidebar.style.transition = 'none';
        body.style.transition = 'none';
        
        if (isMobile()) {
            // Sur mobile : fermer la navbar par défaut
            body.classList.remove('navbar-open');
            bsOffcanvas.hide();
        } else {
            // Sur desktop : ouvrir la navbar par défaut
            body.classList.add('navbar-open');
            bsOffcanvas.show();
        }
        
        // RÉACTIVER LES TRANSITIONS APRÈS UN COURT DÉLAI
        setTimeout(() => {
            sidebar.style.transition = '';
            body.style.transition = '';
        }, 50);
    }

    // ⭐ APPELER L'INITIALISATION (ne pas commenter !)
    initNavbarState();
    
    // Retirer la classe loading après l'initialisation
    setTimeout(() => {
        document.body.classList.remove('loading');
    }, 100);

    // Réinitialiser l'état lors du redimensionnement de la fenêtre
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            initNavbarState();
        }, 250);
    });

    // Toggle avec le bouton chevron
    if (navbarToggle) {
        navbarToggle.addEventListener('click', function() {
            if (body.classList.contains('navbar-open')) {
                body.classList.remove('navbar-open');
                bsOffcanvas.hide();
            } else {
                body.classList.add('navbar-open');
                bsOffcanvas.show();
            }
        });
    }

    // Fermer avec le bouton X interne
    if (navbarClose) {
        navbarClose.addEventListener('click', function() {
            body.classList.remove('navbar-open');
            bsOffcanvas.hide();
        });
    }

    // Synchroniser la classe quand Bootstrap cache/montre l'offcanvas
    sidebar.addEventListener('hidden.bs.offcanvas', function () {
        body.classList.remove('navbar-open');
    });

    sidebar.addEventListener('shown.bs.offcanvas', function () {
        body.classList.add('navbar-open');
    });
});