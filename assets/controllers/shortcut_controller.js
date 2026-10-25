import { Controller } from '@hotwired/stimulus';

// "/" focuses the search field, like in most consoles.
export default class extends Controller {
    static targets = ['input'];

    focus(event) {
        const target = event.target;
        if (event.key !== '/' || target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement || target instanceof HTMLSelectElement) {
            return;
        }
        event.preventDefault();
        this.inputTarget.focus();
    }
}
