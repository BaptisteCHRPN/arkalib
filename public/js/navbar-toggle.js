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
        return window.innerWidth < 992; // Breakpoint lg de Bootstrap
    }

    // Initialiser l'état au chargement
    function initNavbarState() {
        if (isMobile()) {
            // Sur mobile : fermer la navbar par défaut
            body.classList.remove('navbar-open');
            bsOffcanvas.hide();
        } else {
            // Sur desktop : ouvrir la navbar par défaut
            body.classList.add('navbar-open');
            bsOffcanvas.show();
        }
    }

    // Appeler l'initialisation au chargement
    initNavbarState();

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