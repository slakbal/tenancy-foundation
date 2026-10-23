<?php

namespace Slakbal\TenancyFoundation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TenantFactory extends Factory
{
    public function modelName(): string
    {
        return config('tenancy-foundation.models.tenant');
    }

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'subdomain' => Str::slug($name).'-'.Str::lower(Str::random(6)),
        ];
    }
}
