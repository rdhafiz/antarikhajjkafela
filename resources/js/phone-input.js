import intlTelInput from 'intl-tel-input';
import 'intl-tel-input/styles';

/**
 * Phone fields on the public site (input[data-phone]) get a country picker,
 * Bangladesh first, with Google's libphonenumber rules. The field shows the
 * number as typed; the forms send the full international number (phoneValue
 * in app.js) and the server checks it again. Loaded only on pages with one.
 */
// Bangla digits (০-৯) become 0-9: the picker only understands Latin, Arabic and Persian digits.
const bangla = /[০-৯]/g;
const toLatin = (text) => text.replace(bangla, (d) => String(d.charCodeAt(0) - 0x09e6));

function insertText(input, text) {
    input.setRangeText(text, input.selectionStart ?? input.value.length, input.selectionEnd ?? input.value.length, 'end');
    input.dispatchEvent(new InputEvent('input', { bubbles: true, inputType: 'insertText', data: text }));
}

/** Listeners that run before the picker's own, so a Bangla keyboard works. */
function acceptBanglaDigits(input) {
    input.addEventListener('keydown', (e) => {
        if (/^[০-৯]$/.test(e.key)) {
            e.preventDefault();
            e.stopImmediatePropagation();
            insertText(input, toLatin(e.key));
        }
    }, true);
    input.addEventListener('paste', (e) => {
        const text = e.clipboardData?.getData('text') ?? '';
        if (/[০-৯]/.test(text)) {
            e.preventDefault();
            e.stopImmediatePropagation();
            insertText(input, toLatin(text));
        }
    }, true);
    // Phone keyboards send no usable key code: fix the value as it arrives.
    input.addEventListener('input', () => {
        if (/[০-৯]/.test(input.value)) {
            const caret = input.selectionStart;
            input.value = toLatin(input.value);
            input.setSelectionRange(caret, caret);
        }
    }, true);
}

export default async function phoneInputs(fields) {
    const bangla = document.documentElement.lang.startsWith('bn');
    const uiTranslations = bangla ? (await import('intl-tel-input/locale/bn')).default : undefined;

    for (const input of fields) {
        if (input.iti) continue;

        acceptBanglaDigits(input);
        if (/[০-৯]/.test(input.value)) input.value = toLatin(input.value);

        input.iti = intlTelInput(input, {
            initialCountry: 'bd',
            countryOrder: ['bd', 'sa', 'ae', 'qa', 'kw', 'om', 'bh', 'my', 'sg', 'gb', 'us'],
            separateDialCode: true,
            strictMode: true,
            countrySearch: true,
            countryNameLocale: bangla ? 'bn' : 'en',
            uiTranslations,
            placeholderNumberType: 'MOBILE',
            loadUtils: () => import('intl-tel-input/utils'),
        });

        // The number rules arrive separately; until then the forms send and check the field as typed.
        input.iti.promise.then(() => (input.phoneReady = true), () => {});
    }
}
