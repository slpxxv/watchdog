import { Controller } from '@hotwired/stimulus';

// Copies the text of a target (or a value) and shows "Skopiowano" on the button.
export default class extends Controller {
    static targets = ['source', 'button'];
    static values = { text: String };

    async copy(event) {
        const button = event.currentTarget;
        const text = this.hasTextValue && this.textValue ? this.textValue : this.sourceTarget.textContent;
        await navigator.clipboard.writeText(text.trim());
        const label = button.textContent;
        button.textContent = 'Skopiowano';
        setTimeout(() => { button.textContent = label; }, 2000);
    }
}
