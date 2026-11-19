<?php

use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Filament\Components\Forms\MediaTypeUpload;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Columns\MediaTypeImageColumn;
use Slakbal\TenancyFoundation\Models\Concerns\RegistersMediaTypes;
use Slakbal\TenancyFoundation\Support\Media\MediaPathGenerator;
use Slakbal\TenancyFoundation\TenancyFoundationServiceProvider;
use Slakbal\TenancyFoundation\Tests\Support\TestMediaTypes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;
use Spatie\MediaLibrary\Support\PathGenerator\PathGeneratorFactory;

function modelWithMediaTypes(): HasMedia
{
    return new class extends Model implements HasMedia
    {
        use InteractsWithMedia, RegistersMediaTypes;

        public function registerMediaCollections(): void
        {
            $this->registerMediaType(TestMediaTypes::AVATAR_IMAGE);
            $this->registerMediaType(TestMediaTypes::RESUME_PDF);
        }
    };
}

test('a model registers a collection per media type with its disk, mime types and single file setting', function (TestMediaTypes $type) {
    $model = modelWithMediaTypes();
    $model->registerMediaCollections();

    $collection = collect($model->mediaCollections)->firstWhere('name', $type->getCollection());

    expect($collection->diskName)->toBe($type->getDisk())
        ->and($collection->acceptsMimeTypes)->toBe($type->getMimeTypes())
        ->and($collection->singleFile)->toBeTrue();
})->with(TestMediaTypes::cases());

test('the image type registers its conversions and the pdf type none', function () {
    app()->register(MediaLibraryServiceProvider::class);
    $model = modelWithMediaTypes();
    $model->registerMediaCollections();

    $model->registerAllMediaConversions();

    expect(collect($model->mediaConversions)->map->getName()->all())->toBe(['small', 'large'])
        ->and($model->mediaConversions[0]->shouldBeQueued())->toBeFalse()
        ->and($model->mediaConversions[1]->shouldBeQueued())->toBeTrue();
});

test('the path generator is off until media.type_enum is set', function () {
    expect(config('tenancy-foundation.media.type_enum'))->toBeNull();
});

test('the media path generator stores a file in the folder of its media type', function () {
    config(['tenancy-foundation.media.type_enum' => TestMediaTypes::class]);
    $media = new Media;
    $media->forceFill(['id' => 7, 'collection_name' => TestMediaTypes::RESUME_PDF->getCollection()]);

    expect((new MediaPathGenerator)->getPath($media))->toBe('person/resume/7/')
        ->and((new MediaPathGenerator)->getPathForConversions($media))->toBe('person/resume/7/conversions/');
});

test('the service provider registers the path generator for every HasMedia model when the enum is configured', function () {
    config(['tenancy-foundation.media.type_enum' => TestMediaTypes::class]);
    app()->getProvider(TenancyFoundationServiceProvider::class)->boot();

    $media = new Media;
    $media->forceFill(['id' => 3, 'model_type' => modelWithMediaTypes()::class, 'collection_name' => TestMediaTypes::AVATAR_IMAGE->getCollection()]);

    expect(PathGeneratorFactory::create($media))->toBeInstanceOf(MediaPathGenerator::class);
});

test('the upload component is set up from the media type', function (TestMediaTypes $type) {
    $upload = MediaTypeUpload::make('image', $type);

    expect($upload->getCollection())->toBe($type->getCollection())
        ->and($upload->getAcceptedFileTypes())->toBe($type->getMimeTypes())
        ->and($upload->hasImageEditor())->toBeTrue();
})->with([TestMediaTypes::AVATAR_IMAGE]);

test('the image column shows the first conversion of the media type', function () {
    $column = MediaTypeImageColumn::make('image', TestMediaTypes::AVATAR_IMAGE);

    expect($column->getCollection())->toBe(TestMediaTypes::AVATAR_IMAGE->getCollection())
        ->and($column->getConversion())->toBe('small');
});
