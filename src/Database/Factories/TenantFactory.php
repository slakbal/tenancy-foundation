<?php

namespace Slakbal\TenancyFoundation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Slakbal\TenancyFoundation\Models\Tenant;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    public function modelName(): string
    {
        return config('tenancy-foundation.models.tenant');
    }

    public function definition(): array
    {
        $name = fake()->unique()->company();
        $slug = Str::slug($name).'-'.Str::lower(Str::random(6));

        return [
            'name' => $name,
            'subdomain' => $slug,
            'domain' => $slug.'.example.test',
        ];
    }
}
