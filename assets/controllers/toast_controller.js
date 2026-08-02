import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["progress", "token"];
    static values = { duration: Number, restoreUrl: String };

    connect() {
        this.timeout = setTimeout(
            () => this.dismiss(),
            this.durationValue || 6000,
        );

        if (this.hasProgressTarget) {
            this.progressTarget.style.transitionDuration = `${this.durationValue || 6000}ms`;
            requestAnimationFrame(() => {
                this.progressTarget.style.width = "0%";
            });
        }
    }

    disconnect() {
        clearTimeout(this.timeout);
    }

    async undo(event) {
        event.preventDefault();
        clearTimeout(this.timeout);

        const formData = new FormData();
        formData.append("_token", this.tokenTarget.value);

        const response = await fetch(this.restoreUrlValue, {
            method: "POST",
            body: formData,
        });

        if (response.ok) {
            sessionStorage.setItem("scrollY", window.scrollY);
            window.location.reload();
        }

        this.dismiss();
    }

    dismiss() {
        this.element.remove();
    }
}
