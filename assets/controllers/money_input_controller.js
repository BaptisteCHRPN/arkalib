import { Controller } from '@hotwired/stimulus';
import AutoNumeric from 'autonumeric';

export default class extends Controller {
    connect() {
        this.autoNumeric = new AutoNumeric(this.element, {
            decimalCharacter: ',',
            digitGroupSeparator: '',
            decimalPlaces: 2,
        });

        this.element.addEventListener('paste', this.onPaste.bind(this));
    }

    disconnect() {
        this.autoNumeric.remove();
    }

    onPaste(event) {
        const text = (event.clipboardData || window.clipboardData).getData('text').trim();
        const value = this.parseAmount(text);

        if (value === null) {
            return;
        }

        event.preventDefault();
        this.autoNumeric.set(value);
    }

    parseAmount(text) {
        const cleaned = text.replace(/[^\d,.\-]/g, ''); // enlève €, espaces, etc.

        if (!cleaned) {
            return null;
        }

        const lastComma = cleaned.lastIndexOf(',');
        const lastDot = cleaned.lastIndexOf('.');
        let decimalIndex = -1;

        if (lastComma !== -1 && lastDot !== -1) {
            // les deux présents : le dernier des deux est forcément le séparateur décimal
            decimalIndex = Math.max(lastComma, lastDot);
        } else if (lastComma !== -1 || lastDot !== -1) {
            const index = lastComma !== -1 ? lastComma : lastDot;
            const separatorChar = lastComma !== -1 ? ',' : '.';
            const occurrences = cleaned.split(separatorChar).length - 1;
            const digitsAfter = cleaned.length - index - 1;

            // un seul séparateur suivi d'1 ou 2 chiffres => décimal, sinon séparateur de milliers
            if (occurrences === 1 && digitsAfter <= 2) {
                decimalIndex = index;
            }
        }

        const integerPart = (decimalIndex !== -1 ? cleaned.slice(0, decimalIndex) : cleaned).replace(/[,.]/g, '');
        const decimalPart = decimalIndex !== -1 ? cleaned.slice(decimalIndex + 1).replace(/[,.]/g, '') : '';

        const value = parseFloat(decimalPart ? `${integerPart}.${decimalPart}` : integerPart);

        return Number.isNaN(value) ? null : value;
    }
}
