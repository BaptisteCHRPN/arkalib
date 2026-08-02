import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['frame'];

    load(event) {
        this.frameTarget.src = event.relatedTarget.dataset.url;
    }
}
