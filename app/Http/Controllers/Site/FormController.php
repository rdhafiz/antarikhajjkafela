<?php

namespace App\Http\Controllers\Site;

use App\Domain\Forms\LeadFields;
use App\Domain\Forms\Recaptcha;
use App\Http\Controllers\Controller;
use App\Mail\InquiryReceived;
use App\Models\Inquiry;
use App\Models\PageSection;
use App\PageBuilder\ContentProcessor;
use App\Rules\PlainText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Propaganistas\LaravelPhone\Rules\Phone;
use Throwable;

/**
 * Receives contact-form and lead-form submissions from page sections.
 */
class FormController extends Controller
{
    public function submit(Request $request, PageSection $section, Recaptcha $recaptcha, ContentProcessor $content): JsonResponse|RedirectResponse
    {
        abort_unless(in_array($section->component, ['contact_form', 'lead_form'], true), 404);
        abort_unless($section->is_visible && $section->page?->isVisibleToPublic(), 404);

        // Bots fill the hidden honeypot field; pretend success and store nothing.
        if (filled($request->input('website'))) {
            return $this->success($request, $section);
        }

        // Browsers count a line break as one character but send two (CRLF): count it the way the box did.
        if (is_string($request->input('message'))) {
            $request->merge(['message' => str_replace("\r\n", "\n", $request->input('message'))]);
        }

        $data = $request->validate($this->rules($section, $request), $this->messages(), $this->attributes($section));

        // Multi-city: the flights after the first, stored as a readable list.
        if (isset($data['legs'])) {
            $data['more_flights'] = array_values(array_map(fn ($leg) => array_map(fn ($v) => trim((string) $v), $leg), $data['legs']));
            unset($data['legs']);
        }

        if (! $recaptcha->passes($request->input('recaptcha_token'), $request->ip())) {
            throw ValidationException::withMessages(['recaptcha' => __('We could not verify your submission. Please try again.')])
                ->errorBag('default');
        }

        $inquiry = Inquiry::create([
            'source' => Inquiry::sourceFor($section, $data),
            'page_id' => $section->page_id,
            'page_section_id' => $section->id,
            'name' => $data['name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'data' => array_filter(
                collect($data)->except(['name', 'phone', 'email'])->all(),
                fn ($value) => filled($value),
            ) + ['page' => $content->translate($section->page->getTranslations('title'), 'en')],
            'locale' => app()->getLocale(),
            'ip' => $request->ip(),
        ]);

        $this->notify($inquiry);

        return $this->success($request, $section);
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function rules(PageSection $section, Request $request): array
    {
        if ($section->component === 'contact_form') {
            return [
                'name' => ['required', 'string', 'max:120', new PlainText],
                'phone' => ['required', 'string', 'max:25', (new Phone)->international()->country('BD')->type('mobile')],
                'email' => ['nullable', 'email', 'max:190'],
                'subject' => ['nullable', 'string', 'max:160', new PlainText],
                'message' => ['required', 'string', 'max:2000'],
            ];
        }

        $catalogue = LeadFields::all();
        $rules = [];

        foreach ($section->content['fields'] ?? [] as $field) {
            $key = $field['field'] ?? null;

            if (isset($catalogue[$key])) {
                $rules[$key] = [($field['required'] ?? false) ? 'required' : 'nullable', ...$catalogue[$key]['rules']];
            }
        }

        // Choices typed in the admin, one per line (in any language).
        foreach (['package' => 'package_options', 'visa_country' => 'country_options'] as $key => $source) {
            if (! isset($rules[$key])) {
                continue;
            }

            $options = collect(preg_split('/\r?\n/', (string) collect($section->content[$source] ?? [])->filter()->implode("\n")))
                ->map(fn ($o) => trim($o))
                ->filter()
                ->values()
                ->all();

            if ($options !== []) {
                $rules[$key][] = Rule::in($options);
            }
        }

        // A return flight cannot come back before it leaves.
        if (isset($rules['return_date'], $rules['departure_date'])) {
            $rules['return_date'][] = 'after_or_equal:departure_date';
        }

        // You cannot fly from a city to the same city.
        if (isset($rules['to_city'], $rules['from_city'])) {
            $rules['to_city'][] = $this->differentCity(fn () => $request->input('from_city'));
        }

        if (isset($rules['trip_type'])) {
            $rules = $this->tripRules($rules, $request);
        }

        return $rules;
    }

    /**
     * The trip type decides the rest: a return trip needs its return date,
     * one way has none, and multi-city adds up to four more flights (each with
     * its own cities and a date no earlier than the flight before it).
     *
     * @param  array<string, list<mixed>>  $rules
     * @return array<string, list<mixed>>
     */
    private function tripRules(array $rules, Request $request): array
    {
        if (isset($rules['return_date'])) {
            $rules['return_date'] = ['exclude_unless:trip_type,Return', 'required', ...array_values(array_diff($rules['return_date'], ['required', 'nullable']))];
        }

        if (isset($rules['from_city'], $rules['to_city'])) {
            $previousDate = function (int $index) use ($request) {
                return $index === 0 ? $request->input('departure_date') : $request->input('legs.'.($index - 1).'.date');
            };

            $rules['legs'] = ['exclude_unless:trip_type,Multi-city', 'required', 'array', 'min:1', 'max:'.LeadFields::MAX_EXTRA_FLIGHTS];
            $rules['legs.*'] = ['array:from,to,date'];
            $rules['legs.*.from'] = ['required', 'string', 'max:80'];
            $rules['legs.*.to'] = ['required', 'string', 'max:80', $this->differentCity(fn (string $attribute) => $request->input(str_replace('.to', '.from', $attribute)))];
            $rules['legs.*.date'] = ['required', 'date', 'after_or_equal:today', function (string $attribute, mixed $value, \Closure $fail) use ($previousDate) {
                $before = $previousDate((int) explode('.', $attribute)[1]);

                if (filled($before) && strtotime((string) $value) !== false && strtotime((string) $before) !== false && strtotime((string) $value) < strtotime((string) $before)) {
                    $fail(__('This flight cannot be earlier than the flight before it.'));
                }
            }];
        }

        return $rules;
    }

    /**
     * Fails when the destination is the departure city (ignoring case and spaces).
     *
     * @param  callable(string): mixed  $from  the departure city for this field
     */
    private function differentCity(callable $from): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($from) {
            $normalize = fn ($city) => mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) $city)));

            if (filled($value) && $normalize($value) === $normalize($from($attribute))) {
                $fail(__('Flying to must be a different city from Flying from.'));
            }
        };
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'phone.phone' => __('Enter a valid mobile number, e.g. 01712-345678, or an international number starting with +.'),
            'return_date.required' => __('Choose your return date, or pick One way.'),
            'legs.required' => __('Add the next flight of your multi-city trip.'),
            'legs.min' => __('Add the next flight of your multi-city trip.'),
            'legs.max' => __('A multi-city trip can have up to :count flights.', ['count' => LeadFields::MAX_EXTRA_FLIGHTS + 1]),
            // Plain wording for dates (a date typed by hand can be in the past or not a date at all).
            '*.date' => __('Choose a valid date for :attribute.'),
            'departure_date.after_or_equal' => __(':attribute cannot be in the past. Choose today or a later date.'),
            'return_date.after_or_equal' => __(':attribute cannot be before the departure date.'),
            'legs.*.date.after_or_equal' => __(':attribute cannot be in the past. Choose today or a later date.'),
            'message.max' => __('Please keep this to :max characters.'),
        ];
    }

    /**
     * Field names for messages: the label the admin gave the field on this
     * form (e.g. "Planned travel date"), else the standard one.
     *
     * @return array<string, string>
     */
    private function attributes(PageSection $section): array
    {
        $custom = collect($section->component === 'lead_form' ? ($section->content['fields'] ?? []) : [])
            ->filter(fn ($field) => isset($field['field']))
            ->mapWithKeys(function ($field) {
                $label = $field['label'] ?? null;
                $label = is_array($label) ? ($label[app()->getLocale()] ?? $label['en'] ?? null) : $label;

                return [$field['field'] => trim((string) $label)];
            })
            ->filter()
            ->all();

        return $custom + [
            'name' => __('Full name'),
            'phone' => __('Mobile number'),
            'email' => __('Email'),
            'message' => __('Message'),
            'district' => __('District'),
            'thana' => __('Thana / Upazila'),
            'package' => __('Package of interest'),
            'travelers' => __('Number of travelers'),
            'travel_month' => __('Preferred travel month'),
            ...collect(LeadFields::all())->except(['name', 'phone', 'email', 'message', 'district', 'thana', 'package', 'travelers', 'travel_month'])
                ->map(fn ($field) => __($field['label']))->all(),
            'legs.*.from' => __('Flying from'),
            'legs.*.to' => __('Flying to'),
            'legs.*.date' => __('Flight date'),
        ];
    }

    private function notify(Inquiry $inquiry): void
    {
        $to = settings('contact.primary_email');

        if (blank($to)) {
            return;
        }

        try {
            Mail::to($to)->send(new InquiryReceived($inquiry));
        } catch (Throwable $e) {
            // The enquiry is saved; a mail problem must not fail the visitor's request.
            report($e);
        }
    }

    private function success(Request $request, PageSection $section): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'ok']);
        }

        return redirect()->to(url()->previous().'#form-'.$section->id)->with('form_success', $section->id);
    }
}
