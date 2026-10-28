import intlTelInput from 'intl-tel-input';
import 'intl-tel-input/styles';

/**
 * Phone fields on the public site (input[data-phone]) get a country picker,
 * Bangladesh first, with Google's libphonenumber rules. The field shows the
 * number as typed; the forms send the full international number (phoneValue
 * in app.js) and the server checks it again. Loaded only on pages with one.
 */
export default async function phoneInputs(fields) {
    const bangla = document.documentElement.lang.startsWith('bn');
    const uiTranslations = bangla ? (await import('intl-tel-input/locale/bn')).default : undefined;

    for (const input of fields) {
        if (input.iti) continue;

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
