import { Controller } from '@hotwired/stimulus';

// A <select> that navigates as soon as it changes; without JS the form has a submit button.
export default class extends Controller {
    submit() {
        this.element.requestSubmit();
    }
}
