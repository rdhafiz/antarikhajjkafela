<?php

namespace App\Http\Controllers\Site;

use App\Domain\Forms\LeadFields;
use App\Domain\Forms\Recaptcha;
use App\Http\Controllers\Controller;
use App\Mail\InquiryReceived;
use App\Models\Inquiry;
use App\Models\PageSection;
use App\PageBuilder\ContentProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

        $data = $request->validate($this->rules($section), [], $this->attributes());

        if (! $recaptcha->passes($request->input('recaptcha_token'), $request->ip())) {
            throw ValidationException::withMessages(['recaptcha' => __('We could not verify your submission. Please try again.')])
                ->errorBag('default');
        }

        $inquiry = Inquiry::create([
            'source' => $section->component,
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
    private function rules(PageSection $section): array
    {
        if ($section->component === 'contact_form') {
            return [
                'name' => ['required', 'string', 'max:120'],
                'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{7,20}$/'],
                'email' => ['nullable', 'email', 'max:190'],
                'subject' => ['nullable', 'string', 'max:160'],
                'message' => ['required', 'string', 'max:3000'],
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

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    private function attributes(): array
    {
        return [
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
