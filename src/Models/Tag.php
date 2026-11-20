<?php

namespace Slakbal\TenancyFoundation\Models;

use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Slakbal\TenancyFoundation\Database\Factories\TagFactory;
use Slakbal\TenancyFoundation\Models\Concerns\HasUlidKey;

#[Fillable(['name', 'color'])]
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory, HasUlidKey;

    protected static function newFactory(): TagFactory
    {
        return TagFactory::new();
    }

    public function taggedModels(string $class): MorphToMany
    {
        return $this->morphedByMany($class, 'taggable');
    }

    public function badgeColor(): array|string
    {
        return filled($this->color) ? Color::hex($this->color) : 'gray';
    }

    #[Scope]
    protected function withUsageCount(Builder $query): void
    {
        $query->addSelect([
            'uses_count' => DB::table('taggables')->selectRaw('count(*)')->whereColumn('taggables.tag_id', $this->getTable().'.id'),
        ]);
    }

    protected function name(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => filled($value) ? trim($value) : null);
    }

    protected function color(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => filled($value) ? Str::lower(trim($value)) : null);
    }
}
