<?php

/*
 * Extra validation messages. Laravel's own messages still apply for every other rule.
 */
return [
    'min' => [
        // Laravel's own reads "must have at least 1 items".
        'array' => 'Add at least :min to :attribute.',
    ],

    'phone' => 'Enter a valid phone number with its country code, for example +880 1711-381901.',
];
