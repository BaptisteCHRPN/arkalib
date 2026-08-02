import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'submit'];
    static values = { name: String };

    check() {
        this.submitTarget.disabled = this.inputTarget.value.trim() !== this.nameValue;
    }
}
