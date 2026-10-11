<?php

namespace App\PageBuilder\Components;

use App\PageBuilder\Field;

/**
 * The eyebrow / title / intro trio most sections start with.
 */
trait HasHeading
{
    /**
     * @return list<Field>
     */
    protected function headingFields(bool $intro = true, bool $titleRequired = false): array
    {
        $fields = [
            Field::text('eyebrow', 'Eyebrow')->max(60)->hint('Small label above the title.'),
            Field::text('title', 'Title')->max(160)->required($titleRequired),
        ];

        if ($intro) {
            $fields[] = Field::textarea('intro', 'Introduction')->max(600)->rows(3);
        }

        return $fields;
    }
}
