<?php

namespace App\Models;

use App\Models\Concerns\Trashable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

#[Fillable(['name', 'slug', 'sort'])]
class PostCategory extends Model
{
    use HasTranslations, Trashable;

    /** Freed while in the Trash, given back on restore. */
    protected array $releaseOnTrash = ['slug' => 'suffix'];

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
