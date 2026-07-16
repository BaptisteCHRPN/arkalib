import { Controller } from '@hotwired/stimulus';
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';
import { French } from 'flatpickr/dist/l10n/fr.js';

export default class extends Controller {
    connect() {
        this.picker = flatpickr(this.element, {
            locale: French,
            dateFormat: 'd/m/Y',
            allowInput: true,
        });

        this.element.addEventListener('paste', this.onPaste.bind(this));
    }

    disconnect() {
        this.picker.destroy();
    }

    onPaste(event) {
        const text = (event.clipboardData || window.clipboardData).getData('text').trim();
        const parsed = this.parseDate(text);

        if (!parsed) {
            return; // on laisse Flatpickr essayer de parser lui-même (format d/m/Y)
        }

        event.preventDefault();
        this.picker.setDate(parsed, true);
    }

    parseDate(text) {
        let y, m, d;

        let match = text.match(/^(\d{4})-(\d{2})-(\d{2})$/); // ISO: 2026-07-16
        if (match) {
            [, y, m, d] = match;
        } else {
            match = text.match(/^(\d{2})[\/\-](\d{2})[\/\-](\d{4})$/); // 16/07/2026
            if (match) {
                [, d, m, y] = match;
            }
        }

        if (!y) {
            return null;
        }

        return new Date(Number(y), Number(m) - 1, Number(d));
    }
}
