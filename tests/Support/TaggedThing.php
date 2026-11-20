<?php

namespace Slakbal\TenancyFoundation\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Models\Concerns\HasTags;

class TaggedThing extends Model
{
    use HasTags;

    protected $table = 'tagged_things';

    protected $guarded = [];
}
