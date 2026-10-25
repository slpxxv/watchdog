import { Controller } from '@hotwired/stimulus';

// Opens one of several <dialog> targets (data-dialog-name-param on the button names it). Esc and
// the "Anuluj" button (formmethod="dialog") close it; submitting the form posts normally.
export default class extends Controller {
    static targets = ['rename', 'delete', 'dialog'];

    open(event) {
        const dialog = this[`${event.params.name}Target`] ?? this.dialogTarget;
        dialog.showModal();
    }
}
