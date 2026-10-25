import { Controller } from '@hotwired/stimulus';

const SVG = 'http://www.w3.org/2000/svg';
const SEVERE = new Set(['error', 'critical', 'alert', 'emergency']);
const clock = new Intl.DateTimeFormat('pl-PL', { hour: '2-digit', minute: '2-digit', hour12: false });

// Line counts per time slice, drawn from the rows in the page: a sliding window in live mode, the
// searched range otherwise. Also keeps the line counter in the filter bar. SVG attributes (not inline
// styles) keep the page within the CSP.
export default class extends Controller {
    static targets = ['list', 'bars', 'from', 'to', 'count'];
    static values = {
        from: String,
        to: String,
        live: Boolean,
        window: { type: Number, default: 15 * 60_000 },
        buckets: { type: Number, default: 60 },
    };

    connect() {
        this.observer = new MutationObserver(() => this.schedule());
        this.observer.observe(this.listTarget, { childList: true, subtree: true });
        if (this.liveValue) {
            this.timer = setInterval(() => this.render(), 10_000);
        }
        this.render();
    }

    disconnect() {
        this.observer?.disconnect();
        clearInterval(this.timer);
    }

    schedule() {
        cancelAnimationFrame(this.frame);
        this.frame = requestAnimationFrame(() => this.render());
    }

    render() {
        const rows = [...this.listTarget.querySelectorAll('li[data-time]')];
        const to = this.liveValue ? Date.now() : Date.parse(this.toValue) || Date.now();
        const from = this.liveValue ? to - this.windowValue : Date.parse(this.fromValue) || to - 86_400_000;

        const count = this.bucketsValue;
        const size = Math.max(1, (to - from) / count);
        const buckets = Array.from({ length: count }, () => ({ total: 0, severe: 0, warning: 0 }));
        for (const row of rows) {
            const at = Date.parse(row.dataset.time);
            if (at < from || at > to) {
                continue;
            }
            const bucket = buckets[Math.min(count - 1, Math.floor((at - from) / size))];
            bucket.total++;
            if (SEVERE.has(row.dataset.level)) {
                bucket.severe++;
            } else if (row.dataset.level === 'warning') {
                bucket.warning++;
            }
        }
        this.draw(buckets, from, size);

        if (this.hasFromTarget) {
            this.fromTarget.textContent = clock.format(from);
            this.toTarget.textContent = this.liveValue ? 'teraz' : clock.format(to);
        }
        if (this.hasCountTarget) {
            const n = rows.length;
            this.countTarget.textContent = `${n} ${n === 1 ? 'linia' : n % 10 >= 2 && n % 10 <= 4 && !(n % 100 >= 12 && n % 100 <= 14) ? 'linie' : 'linii'}`;
        }
    }

    draw(buckets, from, size) {
        const peak = Math.max(1, ...buckets.map((b) => b.total));
        this.barsTarget.replaceChildren(...buckets.flatMap((b, i) => {
            if (b.total === 0) {
                return [];
            }
            const height = (b.total / peak) * 100;
            const rect = (cls, h, y) => {
                const el = document.createElementNS(SVG, 'rect');
                el.setAttribute('class', cls);
                el.setAttribute('x', String(i + 0.1));
                el.setAttribute('width', '0.8');
                el.setAttribute('y', String(y));
                el.setAttribute('height', String(h));
                return el;
            };
            const group = document.createElementNS(SVG, 'g');
            const title = document.createElementNS(SVG, 'title');
            title.textContent = `${clock.format(from + i * size)}: ${b.total}`;
            const severe = (b.severe / b.total) * height;
            const warning = (b.warning / b.total) * height;
            group.append(
                title,
                rect('volume-bar', height, 100 - height),
                rect('volume-warning', warning, 100 - severe - warning),
                rect('volume-severe', severe, 100 - severe),
            );
            return [group];
        }));
    }
}
