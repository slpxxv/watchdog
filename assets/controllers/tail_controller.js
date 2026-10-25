import { Controller } from '@hotwired/stimulus';

// Live tail: polls the log_tail endpoint for HTML list items and adds the new ones to the list.
//
// The server repeats recent lines on purpose (a row committed late can carry a lower id), so lines
// are kept by id and duplicates dropped; a late line slots in by its id. Only the newest `capacity`
// lines stay in the page. Polling rather than SSE: every open stream would hold a PHP process.
export default class extends Controller {
    static targets = ['list', 'scroller', 'empty', 'error', 'newLines', 'toggle', 'label'];
    static values = {
        url: String,
        lastId: Number,
        interval: { type: Number, default: 2000 },
        capacity: { type: Number, default: 2000 },
    };

    connect() {
        this.paused = false;
        this.newCount = 0;
        this.atBottom = true;
        this.ids = new Set([...this.listTarget.children].map((li) => Number(li.dataset.id)));
        this.onVisibility = () => {
            if (!document.hidden) {
                this.poll();
            }
        };
        document.addEventListener('visibilitychange', this.onVisibility);
        this.timer = setInterval(() => this.poll(), this.intervalValue);
        this.scrollToBottom();
        this.dispatch('updated');
    }

    disconnect() {
        clearInterval(this.timer);
        document.removeEventListener('visibilitychange', this.onVisibility);
    }

    toggle() {
        this.paused = !this.paused;
        this.toggleTarget.classList.toggle('on', !this.paused);
        this.toggleTarget.setAttribute('aria-pressed', String(this.paused));
        this.labelTarget.textContent = this.paused ? 'Wstrzymano' : 'Na żywo';
        if (!this.paused) {
            this.poll();
        }
    }

    scrolled() {
        const el = this.scrollerTarget;
        this.atBottom = el.scrollHeight - el.scrollTop - el.clientHeight < 24;
        if (this.atBottom) {
            this.resetNew();
        }
    }

    jump() {
        this.scrollToBottom();
    }

    async poll() {
        if (document.hidden || this.paused || this.polling) {
            return; // resumes from lastId once visible or unpaused
        }
        this.polling = true;
        try {
            const url = new URL(this.urlValue, window.location.origin);
            url.searchParams.set('after', String(this.lastIdValue));
            const response = await fetch(url, { headers: { Accept: 'text/html' } });
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            this.lastIdValue = Number(response.headers.get('X-Tail-Last-Id') ?? this.lastIdValue);
            const added = this.add(await response.text());
            this.errorTarget.hidden = true;
            if (added > 0) {
                this.afterAdd(added);
            }
        } catch (error) {
            this.errorTarget.textContent = `Nie udało się pobrać logów (${error.message}). Próbuję dalej…`;
            this.errorTarget.hidden = false;
        } finally {
            this.polling = false;
        }
    }

    add(html) {
        const template = document.createElement('template');
        template.innerHTML = html;
        let added = 0;
        for (const li of template.content.querySelectorAll('li[data-id]')) {
            const id = Number(li.dataset.id);
            if (this.ids.has(id)) {
                continue;
            }
            this.ids.add(id);
            const next = [...this.listTarget.children].find((existing) => Number(existing.dataset.id) > id);
            this.listTarget.insertBefore(li, next ?? null);
            added++;
        }
        while (this.listTarget.children.length > this.capacityValue) {
            this.ids.delete(Number(this.listTarget.firstElementChild.dataset.id));
            this.listTarget.firstElementChild.remove();
        }
        return added;
    }

    afterAdd(added) {
        if (this.hasEmptyTarget) {
            this.emptyTarget.hidden = true;
        }
        if (this.atBottom) {
            this.scrollToBottom();
        } else {
            this.newCount += added;
            this.newLinesTarget.textContent = `${this.newCount} ${plural(this.newCount, 'nowa linia', 'nowe linie', 'nowych linii')}`;
            this.newLinesTarget.hidden = false;
        }
        this.dispatch('updated');
    }

    scrollToBottom() {
        this.scrollerTarget.scrollTop = this.scrollerTarget.scrollHeight;
        this.atBottom = true;
        this.resetNew();
    }

    resetNew() {
        this.newCount = 0;
        this.newLinesTarget.hidden = true;
    }
}

function plural(n, one, few, many) {
    if (n === 1) {
        return one;
    }
    return n % 10 >= 2 && n % 10 <= 4 && !(n % 100 >= 12 && n % 100 <= 14) ? few : many;
}
