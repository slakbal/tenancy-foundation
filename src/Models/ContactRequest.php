<?php

namespace Slakbal\TenancyFoundation\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Slakbal\TenancyFoundation\Database\Factories\ContactRequestFactory;
use Slakbal\TenancyFoundation\Enums\ContactSource;
use Slakbal\TenancyFoundation\Enums\ContactStatus;
use Slakbal\TenancyFoundation\Models\Concerns\HasUlidKey;

#[Fillable(['name', 'email', 'message', 'source'])]
class ContactRequest extends Model
{
    /** @use HasFactory<ContactRequestFactory> */
    use HasFactory, HasUlidKey, Prunable;

    protected static function newFactory(): ContactRequestFactory
    {
        return ContactRequestFactory::new();
    }

    protected function casts(): array
    {
        return [
            'source' => ContactSource::class,
            'status' => ContactStatus::class,
            'responded_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function prunable(): Builder
    {
        $days = (int) config('tenancy-foundation.contact_requests.prune_after_days');

        return static::query()
            ->when($days <= 0, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->whereIn('status', ContactStatus::closedValues())
            ->where('closed_at', '<=', now()->subDays($days));
    }

    public function canBeRespondedTo(): bool
    {
        return ! $this->isResponded() && $this->status === ContactStatus::NEW;
    }

    public function canBeDismissed(): bool
    {
        return ! $this->isResponded() && $this->status !== ContactStatus::DISMISSED;
    }

    public function isResponded(): bool
    {
        return $this->responded_at !== null;
    }
}
