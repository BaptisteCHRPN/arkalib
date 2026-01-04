document.addEventListener('DOMContentLoaded', function() {
    // Get elements
    const navbarToggle = document.getElementById('navbarToggle');
    const navbarClose = document.getElementById('navbarClose');
    const sidebar = document.getElementById('sidebarNav');
    const body = document.body;
    
    // Check that Bootstrap is loaded
    if (typeof bootstrap === 'undefined') {
        console.error('Bootstrap n\'est pas chargé !');
        return;
    }
    
    // Bootstrap Offcanvas instance
    const bsOffcanvas = new bootstrap.Offcanvas(sidebar, {
        backdrop: false,
        scroll: true
    });
    // Function to check if we are on mobile
    function isMobile() {
        return window.innerWidth < 992;
    }
    // Initialize state on load WITHOUT animation
    function initNavbarState() {
        // TEMPORARILY DISABLE TRANSITIONS
        sidebar.style.transition = 'none';
        body.style.transition = 'none';
        
        if (isMobile()) {
            // On mobile: close navbar by default
            body.classList.remove('navbar-open');
            bsOffcanvas.hide();
        } else {
            // On desktop: open navbar by default
            body.classList.add('navbar-open');
            bsOffcanvas.show();
        }
        
        // RE-ENABLE TRANSITIONS AFTER A SHORT DELAY
        setTimeout(() => {
            sidebar.style.transition = '';
            body.style.transition = '';
        }, 50);
    }
    // ⭐ CALL INITIALIZATION (do not comment!)
    initNavbarState();
    
    // Remove loading class after initialization
    setTimeout(() => {
        document.body.classList.remove('loading');
    }, 100);
    // Reset state when window is resized
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            initNavbarState();
        }, 250);
    });
    // Toggle with chevron button
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
    // Close with internal X button
    if (navbarClose) {
        navbarClose.addEventListener('click', function() {
            body.classList.remove('navbar-open');
            bsOffcanvas.hide();
        });
    }
    // Synchronize class when Bootstrap hides/shows offcanvas
    sidebar.addEventListener('hidden.bs.offcanvas', function () {
        body.classList.remove('navbar-open');
    });
    sidebar.addEventListener('shown.bs.offcanvas', function () {
        body.classList.add('navbar-open');
    });
});