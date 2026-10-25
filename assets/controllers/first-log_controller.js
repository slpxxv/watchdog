import { Controller } from '@hotwired/stimulus';

// On the "source is ready" page: polls the tail endpoint (filtered to the new source) until the
// first line arrives, so the person sees that the setup works.
export default class extends Controller {
    static targets = ['status', 'text'];
    static values = { url: String, interval: { type: Number, default: 2000 } };

    connect() {
        this.timer = setInterval(() => this.check(), this.intervalValue);
    }

    disconnect() {
        clearInterval(this.timer);
    }

    async check() {
        if (document.hidden) {
            return;
        }
        try {
            const url = new URL(this.urlValue, window.location.origin);
            url.searchParams.set('after', '0');
            const response = await fetch(url, { headers: { Accept: 'text/html' } });
            if (response.ok && Number(response.headers.get('X-Tail-Last-Id') ?? 0) > 0) {
                this.statusTarget.classList.add('ok');
                this.textTarget.textContent = 'Odebrano pierwszy log.';
                clearInterval(this.timer);
            }
        } catch {
            // the status is only a hint; the next tick tries again
        }
    }
}
