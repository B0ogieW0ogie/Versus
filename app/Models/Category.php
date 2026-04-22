<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'name_en', 'name_ru', 'sort_order'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    /**
     * @return HasMany<Battle, $this>
     */
    public function battles(): HasMany
    {
        return $this->hasMany(Battle::class);
    }

    public function getLocalizedNameAttribute(): string
    {
        return app()->getLocale() === 'ru' ? $this->name_ru : $this->name_en;
    }
}
