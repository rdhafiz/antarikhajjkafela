<?php

namespace App\PageBuilder\Components;

use App\PageBuilder\Component;
use App\PageBuilder\Field;

class TeamComponent extends Component
{
    use HasHeading;

    public function key(): string
    {
        return 'team';
    }

    public function name(): string
    {
        return 'Team';
    }

    public function description(): string
    {
        return 'Leadership, scholars and guides with photos and roles.';
    }

    public function icon(): string
    {
        return 'users';
    }

    public function fields(): array
    {
        return [
            ...$this->headingFields(),
            Field::repeater('members', 'People', [
                Field::image('photo', 'Photo', 'avatar'),
                Field::text('name', 'Name')->max(80)->required(),
                Field::text('role', 'Role')->max(80),
                Field::textarea('bio', 'Short bio')->max(300)->rows(2),
            ])->max(24)->itemLabel('name'),
        ];
    }

    public function defaultStyle(): array
    {
        return ['background' => 'white', 'spacing' => 'lg'];
    }
}
