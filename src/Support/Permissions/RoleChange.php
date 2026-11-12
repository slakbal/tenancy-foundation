<?php

namespace Slakbal\TenancyFoundation\Support\Permissions;

use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Enums\FilamentPanel;

/**
 * One role's pending permission change: what the page would add and remove, and the full set to sync.
 */
final readonly class RoleChange
{
    /**
     * @param  array<int, string>  $added
     * @param  array<int, string>  $removed
     * @param  array<int, string>  $requested
     */
    public function __construct(
        public FilamentPanel $panel,
        public Model $role,
        public array $added,
        public array $removed,
        public array $requested,
    ) {}

    public function isEmpty(): bool
    {
        return $this->added === [] && $this->removed === [];
    }
}
