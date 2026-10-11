<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

#[Fillable(['name', 'slug', 'sort'])]
class PostCategory extends Model
{
    use HasTranslations;

    /** @var list<string> */
    public array $translatable = ['name'];

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'category_id');
    }

    public function url(): string
    {
        return url('blog').'?category='.$this->slug;
    }
}
