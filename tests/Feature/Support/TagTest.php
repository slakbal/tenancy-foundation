<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Slakbal\TenancyFoundation\Enums\BadgeColor;
use Slakbal\TenancyFoundation\Filament\Components\Forms\ColorInput;
use Slakbal\TenancyFoundation\Models\Tag;
use Slakbal\TenancyFoundation\Tests\Support\TaggedThing;

beforeEach(function () {
    Schema::create('tagged_things', function ($table) {
        $table->id();
        $table->timestamps();
    });
});

test('a tag name is trimmed, the colour lower cased and a missing colour renders the default colour', function () {
    $tag = Tag::factory()->create(['name' => ' KI ', 'color' => '#0EA5E9']);

    expect($tag->name)->toBe('KI')
        ->and($tag->color)->toBe('#0ea5e9')
        ->and($tag->badgeColor())->toBeArray()
        ->and(Tag::factory()->create(['color' => null])->badgeColor())->toBe(BadgeColor::DEFAULT->value);
});

test('any model with the trait can be tagged and the tag finds its models, also through the registered relation', function () {
    $tag = Tag::factory()->create();
    $first = TaggedThing::query()->create();
    $second = TaggedThing::query()->create();
    $first->tags()->attach($tag);
    $second->tags()->attach($tag);

    expect($tag->taggedModels(TaggedThing::class)->count())->toBe(2)
        ->and($tag->taggedThings()->count())->toBe(2)
        ->and($first->tags->pluck('id')->all())->toBe([$tag->id]);
});

test('the usage count covers every model type', function () {
    $tag = Tag::factory()->create();
    $unused = Tag::factory()->create();
    TaggedThing::query()->create()->tags()->attach($tag);
    TaggedThing::query()->create()->tags()->attach($tag);

    $counts = Tag::query()->withUsageCount()->pluck('uses_count', 'id');

    expect((int) $counts[$tag->id])->toBe(2)
        ->and((int) $counts[$unused->id])->toBe(0);
});

test('a tag name is unique and deleting a tag removes its pivot rows', function () {
    $tag = Tag::factory()->create(['name' => 'MaKo']);
    TaggedThing::query()->create()->tags()->attach($tag);

    expect(fn () => Tag::factory()->create(['name' => 'MaKo']))->toThrow(QueryException::class);

    $tag->delete();

    expect(DB::table('taggables')->count())->toBe(0);
});

test('the colour input pre-selects the default badge colour', function () {
    $picker = ColorInput::make('color');

    expect(BadgeColor::DEFAULT->value)->toBe('info')
        ->and(BadgeColor::DEFAULT->getHex())->toBe('#3b82f6')
        ->and($picker->getDefaultState())->toBe('#3b82f6');
});
