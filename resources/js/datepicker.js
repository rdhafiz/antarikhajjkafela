/*
 * Date fields of the public forms ([data-datepicker]) as flatpickr calendars,
 * loaded only on pages that have one. The submitted value stays YYYY-MM-DD;
 * visitors see "12 Nov 2026" in a text field that takes over the field's id,
 * so its label still points at it. A return date can't be before the
 * departure date picked in the same form, nor a multi-city flight before the one it follows.
 */
import flatpickr from 'flatpickr';
import { Bangla } from 'flatpickr/dist/l10n/bn.js';
import 'flatpickr/dist/flatpickr.min.css';
import '../css/datepicker.css';

export default function datepickers(inputs) {
    const bangla = document.documentElement.lang === 'bn';

    inputs.forEach((input) => {
        const id = input.id;
        const picker = flatpickr(input, {
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'j M Y',
            altInputClass: input.className,
            minDate: input.min || 'today',
            disableMobile: true,
            locale: bangla ? Bangla : 'default',
            onChange: () => input.dispatchEvent(new Event('input', { bubbles: true })),
        });

        picker.altInput.id = id;
        input.id = `${id}-value`;
        picker.altInput.placeholder = input.dataset.placeholder || '';
        if (input.required) picker.altInput.required = true;
        input._flatpickr = picker;
    });

    // A later flight (data-min-from="<field name>") cannot be before the date it follows.
    inputs.forEach((input) => {
        const before = input.dataset.minFrom && input.form?.querySelector(`[name="${input.dataset.minFrom}"]`)?._flatpickr;
        if (!before) return;
        before.config.onChange.push(([date]) => {
            input._flatpickr.set('minDate', date || 'today');
            if (date && input._flatpickr.selectedDates[0] && input._flatpickr.selectedDates[0] < date) input._flatpickr.clear();
        });
    });

    // A return date follows the departure date chosen in the same form.
    inputs.forEach((input) => {
        if (input.name !== 'departure_date') return;
        const back = input.form?.querySelector('[name="return_date"]')?._flatpickr;
        if (!back) return;
        input._flatpickr.config.onChange.push(([date]) => {
            back.set('minDate', date || 'today');
            if (date && back.selectedDates[0] && back.selectedDates[0] < date) back.clear();
        });
    });
}
