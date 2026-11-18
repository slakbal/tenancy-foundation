<?php

namespace Slakbal\TenancyFoundation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Slakbal\TenancyFoundation\Enums\DomainRuleType;

class DomainRuleFactory extends Factory
{
    public function modelName(): string
    {
        return config('tenancy-foundation.models.domain_rule');
    }

    public function definition(): array
    {
        return [
            'tenant_id' => config('tenancy-foundation.models.tenant')::factory(),
            'value' => fake()->unique()->domainName(),
            'type' => DomainRuleType::BLOCK,
        ];
    }

    public function allow(): static
    {
        return $this->state(['type' => DomainRuleType::ALLOW]);
    }
}
