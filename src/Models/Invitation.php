<?php

namespace Slakbal\TenancyFoundation\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Slakbal\TenancyFoundation\Database\Factories\InvitationFactory;
use Slakbal\TenancyFoundation\Events\Invitation\InvitationIssued;
use Slakbal\TenancyFoundation\Models\Concerns\HasUlidKey;
use Slakbal\TenancyFoundation\Models\Concerns\Tenancy\BelongsToTenant;

#[Hidden(['invited_by', 'token'])]
#[Fillable(['tenant_id', 'email', 'token', 'invited_by', 'role_id', 'accepted_at', 'expires_at'])]
class Invitation extends Model
{
    /** @use HasFactory<InvitationFactory> */
    use BelongsToTenant, HasFactory, HasUlidKey, Prunable;

    protected static function newFactory(): InvitationFactory
    {
        return InvitationFactory::new();
    }

    protected function casts(): array
    {
        return [
            'accepted_at' => 'immutable_datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(config('tenancy-foundation.models.user'), 'invited_by');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(config('tenancy-foundation.models.role'));
    }

    /**
     * Gives the invitation a fresh link and expiry, saves it and announces it so the invitee is mailed.
     */
    public function issue(): static
    {
        $this->forceFill([
            'token' => Str::random(64),
            'expires_at' => now()->addDays((int) config('tenancy-foundation.invitations.expiry_days')),
        ])->save();

        InvitationIssued::dispatch($this);

        return $this;
    }

    public function prunable(): Builder
    {
        return static::query()->expired();
    }

    #[Scope]
    protected function pending(Builder $query): Builder
    {
        return $query->whereNull('accepted_at')
            ->where('expires_at', '>', now());
    }

    #[Scope]
    protected function expired(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->where(function (Builder $query) {
                $query->whereNull('accepted_at')
                    ->where('expires_at', '<', now());
            })->orWhere(function (Builder $query) {
                $query->whereNotNull('accepted_at')
                    ->where('accepted_at', '<', now()->subDays((int) config('tenancy-foundation.invitations.retention_days')));
            });
        });
    }
}
