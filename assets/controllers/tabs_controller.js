import { Controller } from '@hotwired/stimulus';

// Shows the panel whose name matches the pressed tab button.
export default class extends Controller {
    static targets = ['tab', 'panel'];

    select(event) {
        const name = event.params.name;
        this.tabTargets.forEach((tab) => tab.setAttribute('aria-pressed', String(tab.dataset.tabsNameParam === name)));
        this.panelTargets.forEach((panel) => { panel.hidden = panel.dataset.name !== name; });
    }
}
