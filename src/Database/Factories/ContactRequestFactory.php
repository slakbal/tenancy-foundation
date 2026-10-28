<?php

namespace Slakbal\TenancyFoundation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Slakbal\TenancyFoundation\Enums\ContactSource;
use Slakbal\TenancyFoundation\Enums\ContactStatus;

class ContactRequestFactory extends Factory
{
    public function modelName(): string
    {
        return config('tenancy-foundation.models.contact_request');
    }

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'message' => fake()->paragraph(),
            'source' => ContactSource::PUBLIC,
            'status' => ContactStatus::NEW,
        ];
    }

    public function fromTenant(): static
    {
        return $this->state(['source' => ContactSource::TENANT]);
    }

    public function closed(ContactStatus $status = ContactStatus::COMPLETED, ?\DateTimeInterface $at = null): static
    {
        return $this->state(['status' => $status, 'closed_at' => $at ?? now()]);
    }

    public function responded(string $response = 'Thanks for reaching out.', string $by = 'Support Agent'): static
    {
        return $this->state(['response' => $response, 'responded_at' => now(), 'responded_by' => $by]);
    }
}
