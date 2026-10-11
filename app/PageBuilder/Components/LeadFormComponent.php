<?php

namespace App\PageBuilder\Components;

use App\Domain\Forms\LeadFields;
use App\PageBuilder\Component;
use App\PageBuilder\Field;

class LeadFormComponent extends Component
{
    use HasHeading;

    public function key(): string
    {
        return 'lead_form';
    }

    public function name(): string
    {
        return 'Lead form';
    }

    public function description(): string
    {
        return 'Registration or interest form with the fields you choose. Ideal for campaign pages.';
    }

    public function category(): string
    {
        return 'conversion';
    }

    public function icon(): string
    {
        return 'clipboard-list';
    }

    public function fields(): array
    {
        return [
            ...$this->headingFields(),
            Field::repeater('fields', 'Form fields', [
                Field::select('field', 'Field', LeadFields::options())->required(),
                Field::text('label', 'Custom label')->max(60)->hint('Leave empty to use the standard label.'),
                Field::toggle('required', 'Required'),
            ])->min(1)->max(9)->itemLabel('field'),
            Field::textarea('package_options', 'Package choices')->max(1000)->rows(4)->hint('One per line. Used by the "Package of interest" field.'),
            Field::text('submit_label', 'Button text')->max(40)->placeholder('Register now'),
            Field::textarea('success', 'Thank-you message')->max(300)->rows(2),
            Field::repeater('bullets', 'Points beside the form', [
                Field::text('text', 'Point')->max(140)->required(),
            ])->max(8)->itemLabel('text'),
        ];
    }

    public function defaults(): array
    {
        return [
            ...parent::defaults(),
            'fields' => [
                ['_id' => 'name', 'field' => 'name', 'label' => [], 'required' => true],
                ['_id' => 'phone', 'field' => 'phone', 'label' => [], 'required' => true],
                ['_id' => 'district', 'field' => 'district', 'label' => [], 'required' => false],
                ['_id' => 'email', 'field' => 'email', 'label' => [], 'required' => false],
            ],
        ];
    }

    public function defaultStyle(): array
    {
        return ['background' => 'sand', 'spacing' => 'lg'];
    }
}
