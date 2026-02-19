import './stimulus_bootstrap.js';
import flatpickr from 'flatpickr';
import { French } from 'flatpickr/dist/l10n/fr.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';

flatpickr.localize(French);

const bindDatePickers = (root = document) => {
    const elements = root.querySelectorAll('[data-datepicker]:not([data-datepicker-bound])');
    elements.forEach((element) => {
        const enableTime = element.dataset.datepicker === 'datetime';
        flatpickr(element, {
            enableTime,
            time_24hr: true,
            allowInput: true,
            disableMobile: true,
            dateFormat: enableTime ? "Y-m-d\\TH:i" : 'Y-m-d',
            altInput: true,
            altFormat: enableTime ? 'd/m/Y H:i' : 'd/m/Y',
        });
        element.dataset.datepickerBound = '1';
    });
};

document.addEventListener('turbo:load', () => bindDatePickers(document));
document.addEventListener('turbo:frame-load', (event) => {
    bindDatePickers(event.target);
});
