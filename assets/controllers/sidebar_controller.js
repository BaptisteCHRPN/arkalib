import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["nav", "toggleBtn"];

    connect() {
        this.offcanvas = new bootstrap.Offcanvas(this.navTarget, {
            backdrop: false,
            scroll: true,
        });

        const saved = localStorage.getItem(this.storageKey());
        const shouldBeOpen =
            saved !== null ? saved === "true" : !this.isMobile();

        this.setWithoutAnimation(shouldBeOpen);
        document.body.classList.remove('loading');
    }

    disconnect() {
        if (this.offcanvas) {
            this.offcanvas.dispose();
        }
    }

    storageKey() {
        return this.isMobile() ? "sidebar-open-mobile" : "sidebar-open-desktop";
    }

    isMobile() {
        return window.innerWidth < 992;
    }

    setWithoutAnimation(open) {
        this.navTarget.style.transition = "none";
        document.body.style.transition = "none";

        if (open) {
            document.body.classList.add("navbar-open");
            this.offcanvas.show();
        } else {
            document.body.classList.remove("navbar-open");
            this.offcanvas.hide();
        }

        requestAnimationFrame(() => {
            this.navTarget.style.transition = "";
            document.body.style.transition = "";
        });
    }

    toggle() {
        const isOpen = document.body.classList.contains("navbar-open");
        const willOpen = !isOpen;
        localStorage.setItem(this.storageKey(), willOpen);
        if (willOpen) {
            document.body.classList.add("navbar-open");
            this.offcanvas.show();
        } else {
            document.body.classList.remove("navbar-open");
            this.offcanvas.hide();
        }
    }

    close() {
        localStorage.setItem(this.storageKey(), "false");
        document.body.classList.remove("navbar-open");
        this.offcanvas.hide();
    }
}
