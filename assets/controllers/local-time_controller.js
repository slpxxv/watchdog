import { Controller } from '@hotwired/stimulus';

const formats = {
    time: new Intl.DateTimeFormat('pl-PL', { hour: '2-digit', minute: '2-digit', second: '2-digit', fractionalSecondDigits: 3, hour12: false }),
    clock: new Intl.DateTimeFormat('pl-PL', { hour: '2-digit', minute: '2-digit', hour12: false }),
    day: new Intl.DateTimeFormat('pl-PL', { day: '2-digit', month: '2-digit' }),
    date: new Intl.DateTimeFormat('pl-PL', { day: 'numeric', month: 'short', year: 'numeric' }),
    datetime: new Intl.DateTimeFormat('pl-PL', { dateStyle: 'medium', timeStyle: 'medium' }),
};

// The server prints UTC; this shows the same instant in the viewer's time zone.
// style "log": the time, prefixed with the day when it is not today.
export default class extends Controller {
    static values = { style: { type: String, default: 'datetime' } };

    connect() {
        const date = new Date(this.element.getAttribute('datetime'));
        if (Number.isNaN(date.getTime())) {
            return;
        }
        const style = this.styleValue;
        if (style === 'log') {
            const time = formats.time.format(date);
            this.element.textContent = date.toDateString() === new Date().toDateString() ? time : `${formats.day.format(date)} ${time}`;
        } else {
            this.element.textContent = (formats[style] ?? formats.datetime).format(date);
        }
    }
}
