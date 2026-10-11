<?php

namespace App\Domain\Forms;

/**
 * Fields an admin can switch on in a campaign lead form. Each entry knows
 * its input type, autocomplete hint and validation rules.
 */
class LeadFields
{
    /**
     * @return array<string, array{label: string, type: string, autocomplete: string|null, rules: list<string>}>
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
}
