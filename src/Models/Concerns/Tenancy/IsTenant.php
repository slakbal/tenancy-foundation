<?php

namespace Slakbal\TenancyFoundation\Models\Concerns\Tenancy;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Slakbal\TenancyFoundation\Enums\TenantStatus;
use Slakbal\TenancyFoundation\Models\TenantUser;

// Base Class e.g. Tenant model should implement: HasName, HasCurrentTenantLabel contracts
trait IsTenant
{
    public function initializeIsTenant(): void
    {
        $this->mergeCasts([
            'cancelled_at' => 'datetime',
            'scheduled_for_deletion_at' => 'datetime',
        ]);
    }

    protected function status(): Attribute
    {
        return Attribute::get(fn (): TenantStatus => $this->isCancelled() ? TenantStatus::CANCELLED : TenantStatus::ACTIVE);
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function isDueForDeletion(): bool
    {
        return $this->isCancelled() && $this->scheduled_for_deletion_at?->isPast() === true;
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->whereNull('cancelled_at');
    }

    #[Scope]
    protected function dueForDeletion(Builder $query): Builder
    {
        return $query->whereNotNull('cancelled_at')->where('scheduled_for_deletion_at', '<=', now());
    }

    public function getFilamentName(): string
    {
        return "{$this->name}";
    }

    public function getCurrentTenantLabel(): string
    {
        return __('Active');
    }

    public function owners(): BelongsToMany
    {
        return $this->members()->wherePivot('is_owner', true);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(config('tenancy-foundation.models.user'))
            ->using(TenantUser::class)
            ->withPivot(['id', 'is_owner', 'is_default_tenant', 'status']) // Register pivot attributes
            ->withTimestamps();
    }
}
