<?php

namespace App\Domain\Forms;

/**
 * Fields an admin can switch on in a lead form (campaigns, air ticket quotes,
 * visa enquiries). Each entry knows its input type, autocomplete hint and
 * validation rules; fixed choices are stored in English and shown translated.
 */
class LeadFields
{
    /** Choices of the fixed select fields, by field (value => English label). */
    public const CHOICES = [
        'trip_type' => ['Return' => 'Return', 'One way' => 'One way', 'Multi-city' => 'Multi-city'],
        'cabin_class' => ['Economy' => 'Economy', 'Premium economy' => 'Premium economy', 'Business' => 'Business', 'First' => 'First'],
        'visa_type' => ['Tourist' => 'Tourist visa', 'Business' => 'Business visa', 'Student' => 'Student visa', 'Work' => 'Work visa'],
    ];

    /**
     * @return array<string, array{label: string, type: string, autocomplete: string|null, rules: list<mixed>, min?: int, default?: string|int}>
     */
    public static function all(): array
    {
        return [
            'name' => ['label' => 'Full name', 'type' => 'text', 'autocomplete' => 'name', 'rules' => ['string', 'max:120']],
            'phone' => ['label' => 'Mobile number', 'type' => 'tel', 'autocomplete' => 'tel', 'rules' => ['string', 'max:20', 'regex:/^[0-9+\-\s()]{7,20}$/']],
            'email' => ['label' => 'Email', 'type' => 'email', 'autocomplete' => 'email', 'rules' => ['email', 'max:190']],
            'district' => ['label' => 'District', 'type' => 'text', 'autocomplete' => 'address-level2', 'rules' => ['string', 'max:80']],
            'thana' => ['label' => 'Thana / Upazila', 'type' => 'text', 'autocomplete' => 'address-level3', 'rules' => ['string', 'max:80']],
            'package' => ['label' => 'Package of interest', 'type' => 'select', 'autocomplete' => null, 'rules' => ['string', 'max:120']],
            'travelers' => ['label' => 'Number of travelers', 'type' => 'number', 'autocomplete' => null, 'rules' => ['integer', 'min:1', 'max:50']],
            'travel_month' => ['label' => 'Preferred travel month', 'type' => 'month', 'autocomplete' => null, 'rules' => ['string', 'max:20']],
            // Air ticket quotes.
            'trip_type' => ['label' => 'Trip type', 'type' => 'select', 'autocomplete' => null, 'rules' => ['string', 'in:'.implode(',', array_keys(self::CHOICES['trip_type']))], 'default' => 'Return'],
            'from_city' => ['label' => 'Flying from', 'type' => 'text', 'autocomplete' => null, 'rules' => ['string', 'max:80']],
            'to_city' => ['label' => 'Flying to', 'type' => 'text', 'autocomplete' => null, 'rules' => ['string', 'max:80']],
            'departure_date' => ['label' => 'Departure date', 'type' => 'date', 'autocomplete' => null, 'rules' => ['date', 'after_or_equal:today']],
            'return_date' => ['label' => 'Return date', 'type' => 'date', 'autocomplete' => null, 'rules' => ['date', 'after_or_equal:today']],
            'adults' => ['label' => 'Adults', 'type' => 'number', 'autocomplete' => null, 'rules' => ['integer', 'min:1', 'max:50'], 'min' => 1, 'default' => 1],
            'children' => ['label' => 'Children (2–11)', 'type' => 'number', 'autocomplete' => null, 'rules' => ['integer', 'min:0', 'max:50'], 'min' => 0, 'default' => 0],
            'infants' => ['label' => 'Infants (under 2)', 'type' => 'number', 'autocomplete' => null, 'rules' => ['integer', 'min:0', 'max:20'], 'min' => 0, 'default' => 0],
            'cabin_class' => ['label' => 'Class', 'type' => 'select', 'autocomplete' => null, 'rules' => ['string', 'in:'.implode(',', array_keys(self::CHOICES['cabin_class']))], 'default' => 'Economy'],
            // Visa enquiries.
            'visa_country' => ['label' => 'Destination country', 'type' => 'select', 'autocomplete' => null, 'rules' => ['string', 'max:80']],
            'visa_type' => ['label' => 'Visa type', 'type' => 'select', 'autocomplete' => null, 'rules' => ['string', 'in:'.implode(',', array_keys(self::CHOICES['visa_type']))]],
            'message' => ['label' => 'Message', 'type' => 'textarea', 'autocomplete' => null, 'rules' => ['string', 'max:2000']],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_map(fn ($field) => $field['label'], self::all());
    }

    /**
     * The fixed choices of a field in the visitor's language (value => label).
     *
     * @return array<string, string>
     */
    public static function choices(string $field): array
    {
        return array_map(fn ($label) => __($label), self::CHOICES[$field] ?? []);
    }
}
