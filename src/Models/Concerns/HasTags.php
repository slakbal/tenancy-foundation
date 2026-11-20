<?php

namespace Slakbal\TenancyFoundation\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;
use Slakbal\TenancyFoundation\Models\Tag;

trait HasTags
{
    public static function bootHasTags(): void
    {
        Tag::resolveRelationUsing(
            Str::of(class_basename(static::class))->plural()->camel()->toString(),
            fn (Tag $tag): MorphToMany => $tag->taggedModels(static::class),
        );
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable')->orderBy('tags.name');
    }
}
