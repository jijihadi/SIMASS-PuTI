import { createIcons, icons } from 'lucide';
import flatpickr from 'flatpickr';
import { Indonesian } from 'flatpickr/dist/l10n/id.js';
import { attachClockTimepicker } from './clock-timepicker.js';

createIcons({ icons });
window.createIcons = () => createIcons({ icons });

flatpickr.localize(Indonesian);

/**
 * Set value on a Flatpickr date or Clock timepicker input programmatically.
 */
window.setPickerValue = function (elOrId, value) {
    const el = typeof elOrId === 'string' ? document.getElementById(elOrId) : elOrId;
    if (!el) return;

    const cleanValue = value && value !== '-' ? String(value).trim() : '';

    if (el._flatpickr) {
        if (cleanValue === '') {
            el._flatpickr.clear(true);
        } else {
            el._flatpickr.setDate(cleanValue, true);
        }
    } else {
        el.value = cleanValue;
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
    }
};

/**
 * Standardize all date/datetime inputs with Flatpickr and time inputs with the
 * 24-hour analog clock dial + simple HH:MM direct input.
 */
window.initFlatpickrAuto = function (root = document) {
    // 1. Date inputs -> Flatpickr
    root.querySelectorAll('input[type="date"]:not([data-no-flatpickr])').forEach((el) => {
        if (el._flatpickr) return;

        const fp = flatpickr(el, {
            disableMobile: true,
            // No <select> in the header, else the global Select2 init hijacks it.
            monthSelectorType: 'static',
            minDate: el.min || null,
            maxDate: el.max || null,
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'j F Y',
            altInputClass: el.className,
        });

        // readonly inputs skip `required` validation; block typing instead.
        fp.altInput.readOnly = false;
        fp.altInput.inputMode = 'none';
        fp.altInput.addEventListener('beforeinput', (e) => e.preventDefault());
    });

    // 2. Time inputs -> 24h Material Clock Dial + Simple Direct HH:MM Input
    root.querySelectorAll('input[type="time"]:not([data-no-flatpickr])').forEach((el) => {
        attachClockTimepicker(el);
    });

    // 3. Datetime-local inputs -> Flatpickr
    root.querySelectorAll('input[type="datetime-local"]:not([data-no-flatpickr])').forEach((el) => {
        if (el._flatpickr) return;

        const fp = flatpickr(el, {
            disableMobile: true,
            enableTime: true,
            time_24hr: true,
            monthSelectorType: 'static',
            dateFormat: 'Y-m-d\\TH:i',
            altInput: true,
            altFormat: 'j F Y, H:i',
            altInputClass: el.className,
        });

        fp.altInput.readOnly = false;
        fp.altInput.inputMode = 'none';
        fp.altInput.addEventListener('beforeinput', (e) => e.preventDefault());
    });
};

window.initFlatpickrAuto();

// Calendar lives in <body> and is positioned once on open; re-anchor it on any
// scroll (page, modal, container) so it stays glued to its input.
document.addEventListener('scroll', () => {
    document.querySelectorAll('.flatpickr-input').forEach((el) => {
        if (el._flatpickr?.isOpen) el._flatpickr._positionCalendar();
    });
}, { capture: true, passive: true });

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './realtime.js';
